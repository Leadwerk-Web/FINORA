from __future__ import annotations

import argparse
import json
import os
import re
import socket
import threading
import time
from contextlib import contextmanager
from dataclasses import asdict, dataclass
from datetime import datetime, timezone
from functools import partial
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Iterable
from urllib.error import HTTPError, URLError
from urllib.parse import urljoin, urlparse
from urllib.request import Request, urlopen
from xml.etree import ElementTree

from bs4 import BeautifulSoup
from playwright.sync_api import TimeoutError as PlaywrightTimeoutError
from playwright.sync_api import sync_playwright


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_OUTPUT_ROOT = ROOT / "qa"

STATIC_SEEDS = ("index.html", "en/index.html")
NON_PUBLIC_STATIC = {
    "Bilderliste-FINORA.html",
    "finora_investment_studio.html",
}
WP_LEGAL_HINTS = ("impressum", "datenschutz", "legal-notice", "privacy-policy")

DEFAULT_MOBILE_VIEWPORTS = (
    (300, 900),
    (320, 568),
    (360, 800),
    (375, 812),
    (390, 844),
    (412, 915),
    (414, 896),
    (430, 932),
    (480, 900),
    (540, 960),
    (600, 1024),
    (640, 1136),
    (700, 1200),
    (720, 1280),
    (744, 1133),
    (761, 1200),
)
DEFAULT_DESKTOP_VIEWPORTS = (
    (1280, 720),
    (1366, 768),
    (1440, 900),
    (1512, 982),
    (1600, 900),
    (1728, 1117),
    (1920, 1080),
)
WP_TARGET_MOBILE_WIDTHS = {300, 390, 430, 761}
WP_COOKIE_CAPTURE_WIDTHS = {390, 1366}
FORCED_FULL_CAPTURE_SERVICE_KEYS = {"retirement", "investment"}

NO_MOTION_CSS = """
html,
body {
    scroll-behavior: auto !important;
}

*,
*::before,
*::after {
    animation-delay: 0s !important;
    animation-duration: 0s !important;
    animation-iteration-count: 1 !important;
    transition-delay: 0s !important;
    transition-duration: 0s !important;
    scroll-behavior: auto !important;
}

.anim,
.anim::before,
.anim::after,
.anim.is-visible,
.anim--fade,
.anim--right,
.anim--left,
.anim--scale {
    opacity: 1 !important;
    visibility: visible !important;
    transform: none !important;
    filter: none !important;
}

.cursor-follower,
.back-to-top {
    display: none !important;
}

.timeline-card__details,
.hero-slide,
.hero-slider-track,
.testimonials-track-inner,
.testimonials-grid,
.pillar-card,
.mobile-menu,
.site-header,
.fs-card,
.header-logo img {
    transition: none !important;
}
"""


@dataclass(frozen=True)
class PageRecord:
    env: str
    locale: str
    slug: str
    label: str
    url_path: str
    source: str
    kind: str
    full_capture: bool


class QuietHTTPRequestHandler(SimpleHTTPRequestHandler):
    def log_message(self, format: str, *args) -> None:  # noqa: A003
        return


def utc_now() -> str:
    return datetime.now(timezone.utc).isoformat()


def ensure_dir(path: Path) -> Path:
    path.mkdir(parents=True, exist_ok=True)
    return path


def parse_viewports(raw: str | None, defaults: tuple[tuple[int, int], ...]) -> tuple[tuple[int, int], ...]:
    if not raw:
        return defaults

    viewports: list[tuple[int, int]] = []
    for chunk in raw.split(","):
        item = chunk.strip().lower()
        if not item:
            continue
        match = re.fullmatch(r"(\d+)x(\d+)", item)
        if not match:
            raise ValueError(f"Invalid viewport token: {chunk!r}")
        viewports.append((int(match.group(1)), int(match.group(2))))
    if not viewports:
        raise ValueError("At least one viewport is required.")
    return tuple(viewports)


def canonical_path(path: str) -> str:
    stripped = path.strip()
    if not stripped:
        return "/"
    parsed = urlparse(stripped)
    cleaned = parsed.path or "/"
    if not cleaned.startswith("/"):
        cleaned = "/" + cleaned
    if cleaned != "/" and cleaned.endswith("/"):
        cleaned = cleaned.rstrip("/")
    return cleaned or "/"


def normalize_base_url(base_url: str) -> str:
    value = base_url.strip().rstrip("/")
    if not value:
        raise ValueError("Base URL may not be empty.")
    return value


def load_soup(path: Path) -> BeautifulSoup:
    return BeautifulSoup(path.read_text(encoding="utf-8"), "html.parser")


def is_external_href(href: str) -> bool:
    lowered = href.strip().lower()
    return lowered.startswith(("http://", "https://", "//", "mailto:", "tel:", "#", "data:", "javascript:"))


def normalize_static_href(current_file: Path, href: str) -> str | None:
    cleaned = href.strip()
    if not cleaned or is_external_href(cleaned):
        return None

    parsed = urlparse(cleaned)
    rel_path = parsed.path.strip()
    if not rel_path:
        return None

    if rel_path.startswith("/"):
        candidate = (ROOT / rel_path.lstrip("/")).resolve()
    else:
        candidate = (current_file.parent / rel_path).resolve()

    try:
        relative = candidate.relative_to(ROOT).as_posix()
    except ValueError:
        return None

    if candidate.suffix.lower() != ".html":
        return None
    if not candidate.is_file():
        return None
    if Path(relative).name in NON_PUBLIC_STATIC:
        return None
    return relative


def classify_static_kind(relative_path: str) -> str:
    lowered = relative_path.lower()
    if lowered in {"index.html", "en/index.html"}:
        return "home"
    if any(token in lowered for token in ("impressum", "datenschutz")):
        return "legal"
    if "finora-philosophie" in lowered:
        return "philosophy"
    if "ueber-finora" in lowered:
        return "about"
    if "kontakt" in lowered:
        return "contact"
    return "page"


def locale_from_static_path(relative_path: str) -> str:
    return "en" if relative_path.startswith("en/") else "de"


def slug_from_static_path(relative_path: str) -> str:
    rel = Path(relative_path)
    stem = rel.with_suffix("").as_posix()
    return stem.replace("/", "-")


def discover_static_pages() -> list[PageRecord]:
    queue = [ROOT / seed for seed in STATIC_SEEDS]
    seen: set[str] = set()

    while queue:
        current_file = queue.pop(0)
        relative = current_file.relative_to(ROOT).as_posix()
        if relative in seen:
            continue
        seen.add(relative)

        soup = load_soup(current_file)
        for anchor in soup.select("a[href]"):
            normalized = normalize_static_href(current_file, anchor.get("href", ""))
            if normalized and normalized not in seen:
                queue.append(ROOT / normalized)

    pages: list[PageRecord] = []
    for relative in sorted(seen):
        if Path(relative).name in NON_PUBLIC_STATIC:
            continue
        kind = classify_static_kind(relative)
        pages.append(
            PageRecord(
                env="static",
                locale=locale_from_static_path(relative),
                slug=slug_from_static_path(relative),
                label=relative,
                url_path="/" + relative.replace(os.sep, "/"),
                source=relative,
                kind=kind,
                full_capture=True,
            )
        )
    return pages


def fetch_text(url: str) -> str:
    request = Request(url, headers={"User-Agent": "Finora-Mobile-QA/1.0"})
    with urlopen(request, timeout=30) as response:
        return response.read().decode("utf-8", errors="replace")


def fetch_xml_locations(url: str) -> list[str]:
    try:
        xml_text = fetch_text(url)
    except (HTTPError, URLError):
        return []

    try:
        root = ElementTree.fromstring(xml_text)
    except ElementTree.ParseError:
        soup = BeautifulSoup(xml_text, "xml")
        return [node.get_text(strip=True) for node in soup.find_all("loc") if node.get_text(strip=True)]

    namespace = {"sm": "http://www.sitemaps.org/schemas/sitemap/0.9"}
    values = [node.text.strip() for node in root.findall(".//sm:loc", namespace) if node.text]
    if values:
        return values
    return [node.text.strip() for node in root.findall(".//loc") if node.text]


def extract_wp_footer_legal_paths(base_url: str, page_path: str) -> list[str]:
    try:
        html = fetch_text(urljoin(base_url + "/", page_path.lstrip("/")))
    except (HTTPError, URLError):
        return []

    soup = BeautifulSoup(html, "html.parser")
    matches: list[str] = []
    for anchor in soup.select("footer a[href], .footer-bottom a[href]"):
        href = anchor.get("href", "").strip()
        if not href:
            continue
        absolute = urljoin(base_url + "/", href)
        parsed = urlparse(absolute)
        if normalize_base_url(f"{parsed.scheme}://{parsed.netloc}") != normalize_base_url(base_url):
            continue
        path = canonical_path(parsed.path)
        if any(token in path for token in WP_LEGAL_HINTS):
            matches.append(path)
    return matches


def classify_wp_kind(path: str) -> str:
    if path in {"/", "/en"}:
        return "home"
    if any(token in path for token in ("impressum", "datenschutz", "legal-notice", "privacy-policy")):
        return "legal"
    if "finora-philosophie" in path or "finora-philosophy" in path:
        return "philosophy"
    if "ueber-finora" in path or "about-finora" in path:
        return "about"
    if "kontakt" in path or "contact" in path:
        return "contact"
    return "page"


def locale_from_wp_path(path: str) -> str:
    return "en" if path == "/en" or path.startswith("/en/") else "de"


def slug_from_wp_path(path: str) -> str:
    if path == "/":
        return "home"
    if path == "/en":
        return "en-home"
    return path.strip("/").replace("/", "-")


def infer_service_key_from_label(label: str) -> str | None:
    lowered = label.lower()
    if "altersvorsorge" in lowered or "retirement-planning" in lowered:
        return "retirement"
    if "investment-beratung" in lowered or "investment-advice" in lowered:
        return "investment"
    if "immobilien-beratung" in lowered or "real-estate-consulting" in lowered:
        return "real_estate"
    if "erbanlage-beratung" in lowered or "estate-planning-consultation" in lowered:
        return "inheritance"
    return None


def extract_static_text_length(relative_path: str) -> int:
    soup = load_soup(ROOT / relative_path)
    text = " ".join(soup.stripped_strings)
    return len(re.findall(r"\S+", text))


def determine_longest_service_keys(static_pages: Iterable[PageRecord]) -> set[str]:
    candidates: list[tuple[str, int]] = []
    for page in static_pages:
        if page.locale != "de":
            continue
        service_key = infer_service_key_from_label(page.label)
        if not service_key:
            continue
        candidates.append((service_key, extract_static_text_length(page.source)))
    candidates.sort(key=lambda item: item[1], reverse=True)
    return {key for key, _ in candidates[:2]}


def discover_wp_pages(base_url: str, longest_service_keys: set[str]) -> list[PageRecord]:
    base_url = normalize_base_url(base_url)
    sitemap_urls = (
        urljoin(base_url + "/", "page-sitemap.xml"),
        urljoin(base_url + "/", "wp-sitemap-posts-page-1.xml"),
    )

    discovered_paths: set[str] = set()
    for sitemap_url in sitemap_urls:
        for loc in fetch_xml_locations(sitemap_url):
            parsed = urlparse(loc)
            origin = normalize_base_url(f"{parsed.scheme}://{parsed.netloc}")
            if origin != base_url:
                continue
            discovered_paths.add(canonical_path(parsed.path))
        if discovered_paths:
            break

    for page_path in ("/", "/en"):
        discovered_paths.update(extract_wp_footer_legal_paths(base_url, page_path))

    pages: list[PageRecord] = []
    for path in sorted(discovered_paths):
        locale = locale_from_wp_path(path)
        kind = classify_wp_kind(path)
        service_key = infer_service_key_from_label(path)
        full_capture = (
            kind in {"home", "philosophy", "about", "legal"}
            or (service_key in longest_service_keys)
            or (service_key in FORCED_FULL_CAPTURE_SERVICE_KEYS)
        )
        pages.append(
            PageRecord(
                env="wp",
                locale=locale,
                slug=slug_from_wp_path(path),
                label=path,
                url_path=path,
                source=path,
                kind=kind,
                full_capture=full_capture,
            )
        )
    return pages


def inventory_payload(static_pages: list[PageRecord], wp_pages: list[PageRecord], longest_service_keys: set[str]) -> dict:
    return {
        "generatedAt": utc_now(),
        "longestServiceKeys": sorted(longest_service_keys),
        "static": [asdict(page) for page in static_pages],
        "wp": [asdict(page) for page in wp_pages],
    }


def find_open_menu_button(page) -> object | None:
    selectors = (
        ".mobile-menu-toggle",
        "button[aria-label*='Menü']",
        "button[aria-label*='Open menu']",
        "button[aria-label*='Close menu']",
    )
    for selector in selectors:
        locator = page.locator(selector)
        if locator.count():
            return locator.first
    return None


def find_consent_button(page) -> object | None:
    selectors = (
        ".cmplz-manage-consent",
        "button:has-text('Zustimmung verwalten')",
        "button:has-text('Manage consent')",
    )
    for selector in selectors:
        locator = page.locator(selector)
        if locator.count():
            return locator.first
    return None


def save_json(path: Path, payload: dict) -> None:
    ensure_dir(path.parent)
    path.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8")


def viewport_dir_name(width: int, height: int) -> str:
    return f"{width}x{height}"


def settle_page(page) -> None:
    page.wait_for_load_state("domcontentloaded")
    page.wait_for_load_state("networkidle")
    page.add_style_tag(content=NO_MOTION_CSS)
    page.evaluate(
        """
        async () => {
            const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
            document.documentElement.setAttribute('data-qa-no-motion', 'true');
            document.body.classList.add('qa-no-motion');

            document.querySelectorAll('.anim').forEach((el) => {
                el.classList.add('is-visible');
                el.style.opacity = '1';
                el.style.transform = 'none';
                el.style.filter = 'none';
            });

            document.querySelectorAll('video').forEach((video) => {
                try {
                    video.pause();
                } catch (error) {
                    console.debug(error);
                }
            });

            if (document.fonts?.ready) {
                await document.fonts.ready;
            }

            const lazyLoadImages = Array.from(document.querySelectorAll('img[loading="lazy"]'));
            lazyLoadImages.forEach((img) => {
                img.loading = 'eager';
                if (img.dataset?.src && !img.getAttribute('src')) {
                    img.setAttribute('src', img.dataset.src);
                }
            });

            const maxScroll = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
            const step = Math.max(window.innerHeight * 0.75, 1);
            for (let y = 0; y <= maxScroll; y += step) {
                window.scrollTo(0, Math.min(y, maxScroll));
                await wait(140);
            }

            window.scrollTo(0, 0);
            await wait(260);
        }
        """
    )


def collect_metrics(page, viewport_width: int, viewport_height: int) -> dict:
    return page.evaluate(
        """
        ({ viewportWidth, viewportHeight }) => {
            const headerRow = document.querySelector('.header-row');
            const menu = document.querySelector('.mobile-menu');
            const consent = document.querySelector('#cmplz-cookiebanner-container, .cmplz-cookiebanner');
            const menuVisible = !!(menu && (menu.classList.contains('is-open') || getComputedStyle(menu).visibility === 'visible'));
            const headerHeight = headerRow ? Math.round(headerRow.getBoundingClientRect().height * 100) / 100 : null;
            return {
                title: document.title,
                pageUrl: window.location.href,
                scrollWidth: document.documentElement.scrollWidth,
                innerWidth: window.innerWidth,
                innerHeight: window.innerHeight,
                viewportWidth,
                viewportHeight,
                bodyWidth: Math.round(document.body.getBoundingClientRect().width * 100) / 100,
                headerHeight,
                menuVisible,
                consentPresent: !!consent,
                noMotionApplied: document.documentElement.getAttribute('data-qa-no-motion') === 'true',
            };
        }
        """,
        {"viewportWidth": viewport_width, "viewportHeight": viewport_height},
    )


def open_mobile_menu(page) -> bool:
    toggle = find_open_menu_button(page)
    if not toggle:
        return False

    try:
        toggle.click(timeout=3000)
        page.wait_for_timeout(220)
    except PlaywrightTimeoutError:
        return False

    return bool(
        page.evaluate(
            """
            () => {
                const menu = document.querySelector('.mobile-menu');
                if (!menu) {
                    return false;
                }
                const style = getComputedStyle(menu);
                return menu.classList.contains('is-open') || (style.visibility !== 'hidden' && style.opacity !== '0');
            }
            """
        )
    )


def close_mobile_menu(page) -> None:
    toggle = find_open_menu_button(page)
    if not toggle:
        return
    try:
        if page.evaluate("() => document.querySelector('.mobile-menu')?.classList.contains('is-open')"):
            toggle.click(timeout=1500)
            page.wait_for_timeout(120)
    except PlaywrightTimeoutError:
        return


def open_cookie_dialog(page) -> bool:
    consent_button = find_consent_button(page)
    if consent_button:
        try:
            consent_button.click(timeout=3000)
            page.wait_for_timeout(220)
        except PlaywrightTimeoutError:
            return False

    locator = page.locator("#cmplz-cookiebanner-container .cmplz-cookiebanner, .cmplz-cookiebanner")
    if not locator.count():
        return False
    return locator.first.is_visible()


def screenshot_path(base_dir: Path, width: int, height: int, suffix: str) -> Path:
    return base_dir / f"{width}-{suffix}.png"


def capture_page(
    page,
    record: PageRecord,
    base_url: str,
    env: str,
    phase: str,
    width: int,
    height: int,
    viewport_kind: str,
    output_root: Path,
    capture_full: bool,
    capture_menu: bool,
    capture_cookie: bool,
) -> dict:
    console_errors: list[str] = []
    page_errors: list[str] = []
    failed_responses: list[dict] = []

    page.on(
        "console",
        lambda msg, bucket=console_errors: bucket.append(msg.text) if msg.type == "error" else None,
    )
    page.on("pageerror", lambda exc, bucket=page_errors: bucket.append(str(exc)))
    page.on(
        "response",
        lambda response, bucket=failed_responses: bucket.append(
            {
                "status": response.status,
                "url": response.url,
                "resource": response.request.resource_type,
            }
        )
        if response.status >= 400
        else None,
    )

    url = urljoin(base_url + "/", record.url_path.lstrip("/"))
    page.goto(url, wait_until="networkidle", timeout=45000)
    settle_page(page)

    page_dir = ensure_dir(output_root / env / phase / record.slug)
    metrics = collect_metrics(page, width, height)
    captures: dict[str, str] = {}

    hero_path = screenshot_path(page_dir, width, height, "hero")
    page.screenshot(path=str(hero_path), full_page=False)
    captures["hero"] = str(hero_path.relative_to(output_root))

    if capture_full:
        page.evaluate("() => window.scrollTo(0, 0)")
        page.wait_for_timeout(60)
        full_path = screenshot_path(page_dir, width, height, "full")
        page.screenshot(path=str(full_path), full_page=True)
        captures["full"] = str(full_path.relative_to(output_root))

    menu_captured = False
    if capture_menu:
        page.evaluate("() => window.scrollTo(0, 0)")
        page.wait_for_timeout(60)
        if open_mobile_menu(page):
            menu_path = screenshot_path(page_dir, width, height, "menu")
            page.screenshot(path=str(menu_path), full_page=False)
            captures["menu"] = str(menu_path.relative_to(output_root))
            menu_captured = True
            close_mobile_menu(page)

    cookie_captured = False
    accept_button_color = None
    if capture_cookie:
        if open_cookie_dialog(page):
            cookie_path = screenshot_path(page_dir, width, height, "cookie")
            page.screenshot(path=str(cookie_path), full_page=False)
            captures["cookie"] = str(cookie_path.relative_to(output_root))
            cookie_captured = True
            accept_button_color = page.evaluate(
                """
                () => {
                    const button = document.querySelector('.cmplz-btn.cmplz-accept');
                    return button ? getComputedStyle(button).backgroundColor : null;
                }
                """
            )

    return {
        "env": env,
        "phase": phase,
        "viewportKind": viewport_kind,
        "locale": record.locale,
        "slug": record.slug,
        "label": record.label,
        "url": url,
        "width": width,
        "height": height,
        "kind": record.kind,
        "fullCaptureExpected": capture_full,
        "menuCaptureExpected": capture_menu,
        "cookieCaptureExpected": capture_cookie,
        "captures": captures,
        "menuCaptured": menu_captured,
        "cookieCaptured": cookie_captured,
        "acceptButtonColor": accept_button_color,
        "consoleErrors": console_errors,
        "pageErrors": page_errors,
        "failedResponses": failed_responses,
        "metrics": metrics,
    }


def summarize_results(runs: list[dict]) -> dict:
    return {
        "generatedAt": utc_now(),
        "totalRuns": len(runs),
        "totalScreenshots": sum(len(run["captures"]) for run in runs),
        "overflowFailures": sum(1 for run in runs if run["metrics"]["scrollWidth"] > run["metrics"]["innerWidth"] + 1),
        "consoleFailures": sum(1 for run in runs if run["consoleErrors"] or run["pageErrors"]),
        "networkFailures": sum(1 for run in runs if run["failedResponses"]),
        "menuCaptureFailures": sum(1 for run in runs if run["menuCaptureExpected"] and not run["menuCaptured"]),
        "cookieCaptureFailures": sum(1 for run in runs if run["cookieCaptureExpected"] and not run["cookieCaptured"]),
    }


def find_free_port() -> int:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
        sock.bind(("127.0.0.1", 0))
        return int(sock.getsockname()[1])


@contextmanager
def static_server(base_url_static: str | None):
    if base_url_static:
        yield normalize_base_url(base_url_static)
        return

    port = find_free_port()
    handler = partial(QuietHTTPRequestHandler, directory=str(ROOT))
    server = ThreadingHTTPServer(("127.0.0.1", port), handler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    time.sleep(0.25)
    try:
        yield f"http://127.0.0.1:{port}"
    finally:
        server.shutdown()
        server.server_close()
        thread.join(timeout=5)


def run_captures(
    env: str,
    phase: str,
    output_root: Path,
    static_pages: list[PageRecord],
    wp_pages: list[PageRecord],
    base_url_static: str | None,
    base_url_wp: str | None,
    mobile_viewports: tuple[tuple[int, int], ...],
    desktop_viewports: tuple[tuple[int, int], ...],
) -> list[dict]:
    if env not in {"static", "wp", "both"}:
        raise ValueError(f"Unsupported env: {env}")
    if env in {"wp", "both"} and not base_url_wp:
        raise ValueError("A WordPress base URL is required for env=wp|both.")

    runs: list[dict] = []

    with static_server(base_url_static if env in {"static", "both"} else None) as static_base_url:
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(headless=True)
            try:
                if env in {"static", "both"}:
                    runs.extend(
                        run_env_capture_matrix(
                            browser=browser,
                            env_name="static",
                            phase=phase,
                            pages=static_pages,
                            base_url=static_base_url,
                            output_root=output_root,
                            mobile_viewports=mobile_viewports,
                            desktop_viewports=desktop_viewports,
                        )
                    )

                if env in {"wp", "both"}:
                    runs.extend(
                        run_env_capture_matrix(
                            browser=browser,
                            env_name="wp",
                            phase=phase,
                            pages=wp_pages,
                            base_url=normalize_base_url(base_url_wp or ""),
                            output_root=output_root,
                            mobile_viewports=tuple(v for v in mobile_viewports if v[0] in WP_TARGET_MOBILE_WIDTHS),
                            desktop_viewports=desktop_viewports,
                        )
                    )
            finally:
                browser.close()

    return runs


def run_env_capture_matrix(
    browser,
    env_name: str,
    phase: str,
    pages: list[PageRecord],
    base_url: str,
    output_root: Path,
    mobile_viewports: tuple[tuple[int, int], ...],
    desktop_viewports: tuple[tuple[int, int], ...],
) -> list[dict]:
    results: list[dict] = []

    for width, height in mobile_viewports:
        context = browser.new_context(
            viewport={"width": width, "height": height},
            is_mobile=True,
            has_touch=True,
            device_scale_factor=2,
            reduced_motion="reduce",
        )
        try:
            for record in pages:
                page = context.new_page()
                try:
                    capture_full = env_name == "static" or record.full_capture
                    capture_cookie = env_name == "wp" and record.slug in {"home", "en-home"} and width in WP_COOKIE_CAPTURE_WIDTHS
                    results.append(
                        capture_page(
                            page=page,
                            record=record,
                            base_url=base_url,
                            env=env_name,
                            phase=phase,
                            width=width,
                            height=height,
                            viewport_kind="mobile",
                            output_root=output_root,
                            capture_full=capture_full,
                            capture_menu=True,
                            capture_cookie=capture_cookie,
                        )
                    )
                finally:
                    page.close()
        finally:
            context.close()

    for width, height in desktop_viewports:
        context = browser.new_context(
            viewport={"width": width, "height": height},
            is_mobile=False,
            has_touch=False,
            device_scale_factor=1,
            reduced_motion="reduce",
        )
        try:
            for record in pages:
                page = context.new_page()
                try:
                    capture_full = env_name == "static" or record.full_capture
                    capture_cookie = env_name == "wp" and record.slug in {"home", "en-home"} and width in WP_COOKIE_CAPTURE_WIDTHS
                    results.append(
                        capture_page(
                            page=page,
                            record=record,
                            base_url=base_url,
                            env=env_name,
                            phase=phase,
                            width=width,
                            height=height,
                            viewport_kind="desktop",
                            output_root=output_root,
                            capture_full=capture_full,
                            capture_menu=False,
                            capture_cookie=capture_cookie,
                        )
                    )
                finally:
                    page.close()
        finally:
            context.close()

    return results


def build_arg_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="Run the Finora mobile/desktop screenshot audit for static and WordPress environments."
    )
    parser.add_argument("--env", choices=("static", "wp", "both"), default="both")
    parser.add_argument("--phase", choices=("before", "after"), required=True)
    parser.add_argument("--base-url-static", help="Optional static base URL. If omitted, a local HTTP server is started.")
    parser.add_argument("--base-url-wp", default="https://b36zpgzl.myrdbx.io/")
    parser.add_argument("--output-root", default=str(DEFAULT_OUTPUT_ROOT))
    parser.add_argument("--mobile-viewports", help="Comma-separated viewport list, e.g. 390x844,430x932.")
    parser.add_argument("--desktop-viewports", help="Comma-separated viewport list, e.g. 1280x720,1366x768.")
    return parser


def main() -> int:
    parser = build_arg_parser()
    args = parser.parse_args()

    output_root = ensure_dir(Path(args.output_root))
    reports_dir = ensure_dir(output_root / "reports")
    mobile_viewports = parse_viewports(args.mobile_viewports, DEFAULT_MOBILE_VIEWPORTS)
    desktop_viewports = parse_viewports(args.desktop_viewports, DEFAULT_DESKTOP_VIEWPORTS)

    static_pages = discover_static_pages()
    longest_service_keys = determine_longest_service_keys(static_pages)
    wp_pages = discover_wp_pages(args.base_url_wp, longest_service_keys) if args.env in {"wp", "both"} else []

    inventory = inventory_payload(static_pages, wp_pages, longest_service_keys)
    save_json(reports_dir / "page-inventory.json", inventory)

    runs = run_captures(
        env=args.env,
        phase=args.phase,
        output_root=output_root,
        static_pages=static_pages,
        wp_pages=wp_pages,
        base_url_static=args.base_url_static,
        base_url_wp=args.base_url_wp,
        mobile_viewports=mobile_viewports,
        desktop_viewports=desktop_viewports,
    )

    payload = {
        "generatedAt": utc_now(),
        "phase": args.phase,
        "env": args.env,
        "inventory": inventory,
        "summary": summarize_results(runs),
        "runs": runs,
    }
    report_path = reports_dir / f"validation-{args.phase}.json"
    save_json(report_path, payload)
    print(json.dumps(payload["summary"], indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
