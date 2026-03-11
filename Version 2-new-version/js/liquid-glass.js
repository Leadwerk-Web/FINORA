/**
 * Liquid Glass Physics Pipeline.
 * All browsers use the WebGL canvas path fed by a cloned source scene.
 */

const MathUtils = {
    convex_squircle: (x) => Math.pow(1 - Math.pow(1 - x, 4), 1 / 4),
    convex_circle: (x) => Math.sqrt(1 - Math.pow(1 - x, 2)),
    concave: (x) => 1 - Math.sqrt(1 - Math.pow(x, 2)),
    lip: (x) => {
        const convex = Math.pow(1 - Math.pow(1 - Math.min(x * 2, 1), 4), 1 / 4);
        const concave = 1 - Math.sqrt(1 - Math.pow(1 - x, 2)) + 0.1;
        const smootherstep = 6 * Math.pow(x, 5) - 15 * Math.pow(x, 4) + 10 * Math.pow(x, 3);
        return convex * (1 - smootherstep) + concave * smootherstep;
    }
};

class LiquidGlassRenderer {
    constructor() {
        this.canvas = document.createElement("canvas");
        this.gl = this.canvas.getContext("webgl", {
            alpha: true,
            antialias: true,
            premultipliedAlpha: true
        });

        if (!this.gl) {
            throw new Error("WebGL is unavailable.");
        }

        const vertexShaderSource = `
            attribute vec2 a_position;
            attribute vec2 a_texCoord;
            varying vec2 v_texCoord;

            void main() {
                gl_Position = vec4(a_position, 0.0, 1.0);
                v_texCoord = a_texCoord;
            }
        `;

        const fragmentShaderSource = `
            precision mediump float;

            varying vec2 v_texCoord;
            uniform sampler2D u_source;
            uniform sampler2D u_displacement;
            uniform sampler2D u_specular;
            uniform vec2 u_resolution;
            uniform float u_strength;
            uniform float u_blur;
            uniform float u_saturation;
            uniform float u_brightness;
            uniform float u_contrast;
            uniform vec3 u_tintColorA;
            uniform vec3 u_tintColorB;
            uniform float u_tintAlphaA;
            uniform float u_tintAlphaB;
            uniform vec3 u_rimColor;
            uniform float u_rimAlpha;
            uniform vec3 u_shadowColor;
            uniform float u_shadowAlpha;
            uniform float u_fillAlpha;
            uniform float u_specularBoost;

            vec3 tone(vec3 color) {
                float luminance = dot(color, vec3(0.2126, 0.7152, 0.0722));
                vec3 saturated = mix(vec3(luminance), color, u_saturation);
                vec3 contrasted = (saturated - 0.5) * u_contrast + 0.5;
                return contrasted * u_brightness;
            }

            void main() {
                vec2 displacement = (texture2D(u_displacement, v_texCoord).rg - vec2(0.5)) * 2.0;
                float edge = clamp(length(displacement), 0.0, 1.0);
                vec2 offset = displacement * (u_strength / u_resolution);
                vec2 blurStep = vec2(u_blur) / u_resolution;

                vec4 center = texture2D(u_source, clamp(v_texCoord, 0.001, 0.999));
                vec4 sample0 = texture2D(u_source, clamp(v_texCoord + offset, 0.001, 0.999));
                vec4 sample1 = texture2D(u_source, clamp(v_texCoord + offset + vec2(blurStep.x, 0.0), 0.001, 0.999));
                vec4 sample2 = texture2D(u_source, clamp(v_texCoord + offset - vec2(blurStep.x, 0.0), 0.001, 0.999));
                vec4 sample3 = texture2D(u_source, clamp(v_texCoord + offset + vec2(0.0, blurStep.y), 0.001, 0.999));
                vec4 sample4 = texture2D(u_source, clamp(v_texCoord + offset - vec2(0.0, blurStep.y), 0.001, 0.999));
                vec4 sample5 = texture2D(u_source, clamp(v_texCoord + offset + vec2(blurStep.x, blurStep.y), 0.001, 0.999));
                vec4 sample6 = texture2D(u_source, clamp(v_texCoord + offset + vec2(-blurStep.x, blurStep.y), 0.001, 0.999));
                vec4 sample7 = texture2D(u_source, clamp(v_texCoord + offset + vec2(blurStep.x, -blurStep.y), 0.001, 0.999));
                vec4 sample8 = texture2D(u_source, clamp(v_texCoord + offset + vec2(-blurStep.x, -blurStep.y), 0.001, 0.999));

                vec4 refracted = sample0 * 0.28
                    + sample1 * 0.11 + sample2 * 0.11
                    + sample3 * 0.11 + sample4 * 0.11
                    + sample5 * 0.07 + sample6 * 0.07
                    + sample7 * 0.07 + sample8 * 0.07;
                vec4 base = mix(center, refracted, smoothstep(0.04, 0.32, edge));
                vec3 shaded = tone(base.rgb);
                float tintMix = pow(clamp(1.0 - v_texCoord.y, 0.0, 1.0), 1.15);
                vec3 tintColor = mix(u_tintColorB, u_tintColorA, tintMix);
                float tintAlpha = mix(u_tintAlphaB, u_tintAlphaA, tintMix);
                vec3 tinted = mix(shaded, tintColor, tintAlpha);
                float edgeMask = smoothstep(0.08, 0.88, edge);
                float rimMask = pow(edgeMask, 1.35);
                float specWindow = pow(clamp(1.0 - v_texCoord.y, 0.0, 1.0), 2.2) * (1.0 - edgeMask * 0.42);
                vec4 spec = texture2D(u_specular, v_texCoord);
                vec3 highlight = u_rimColor * ((rimMask * u_rimAlpha) + (specWindow * u_rimAlpha * 0.42));
                vec3 sheen = spec.rgb * spec.a * u_specularBoost * u_rimColor;
                float shadowMask = edgeMask * mix(0.72, 1.18, clamp(v_texCoord.y, 0.0, 1.0));
                vec3 combined = tinted + highlight + sheen - (u_shadowColor * shadowMask * u_shadowAlpha);

                gl_FragColor = vec4(clamp(combined, 0.0, 1.0), max(base.a, u_fillAlpha));
            }
        `;

        this.program = this.createProgram(vertexShaderSource, fragmentShaderSource);
        this.gl.useProgram(this.program);

        const positions = new Float32Array([
            -1, -1,
             1, -1,
            -1,  1,
             1,  1
        ]);

        const texCoords = new Float32Array([
            0, 1,
            1, 1,
            0, 0,
            1, 0
        ]);

        this.positionBuffer = this.gl.createBuffer();
        this.gl.bindBuffer(this.gl.ARRAY_BUFFER, this.positionBuffer);
        this.gl.bufferData(this.gl.ARRAY_BUFFER, positions, this.gl.STATIC_DRAW);

        const positionLocation = this.gl.getAttribLocation(this.program, "a_position");
        this.gl.enableVertexAttribArray(positionLocation);
        this.gl.vertexAttribPointer(positionLocation, 2, this.gl.FLOAT, false, 0, 0);

        this.texCoordBuffer = this.gl.createBuffer();
        this.gl.bindBuffer(this.gl.ARRAY_BUFFER, this.texCoordBuffer);
        this.gl.bufferData(this.gl.ARRAY_BUFFER, texCoords, this.gl.STATIC_DRAW);

        const texCoordLocation = this.gl.getAttribLocation(this.program, "a_texCoord");
        this.gl.enableVertexAttribArray(texCoordLocation);
        this.gl.vertexAttribPointer(texCoordLocation, 2, this.gl.FLOAT, false, 0, 0);

        this.sourceTexture = this.createTexture();
        this.displacementTexture = this.createTexture();
        this.specularTexture = this.createTexture();

        this.uniforms = {
            source: this.gl.getUniformLocation(this.program, "u_source"),
            displacement: this.gl.getUniformLocation(this.program, "u_displacement"),
            specular: this.gl.getUniformLocation(this.program, "u_specular"),
            resolution: this.gl.getUniformLocation(this.program, "u_resolution"),
            strength: this.gl.getUniformLocation(this.program, "u_strength"),
            blur: this.gl.getUniformLocation(this.program, "u_blur"),
            saturation: this.gl.getUniformLocation(this.program, "u_saturation"),
            brightness: this.gl.getUniformLocation(this.program, "u_brightness"),
            contrast: this.gl.getUniformLocation(this.program, "u_contrast"),
            tintColorA: this.gl.getUniformLocation(this.program, "u_tintColorA"),
            tintColorB: this.gl.getUniformLocation(this.program, "u_tintColorB"),
            tintAlphaA: this.gl.getUniformLocation(this.program, "u_tintAlphaA"),
            tintAlphaB: this.gl.getUniformLocation(this.program, "u_tintAlphaB"),
            rimColor: this.gl.getUniformLocation(this.program, "u_rimColor"),
            rimAlpha: this.gl.getUniformLocation(this.program, "u_rimAlpha"),
            shadowColor: this.gl.getUniformLocation(this.program, "u_shadowColor"),
            shadowAlpha: this.gl.getUniformLocation(this.program, "u_shadowAlpha"),
            fillAlpha: this.gl.getUniformLocation(this.program, "u_fillAlpha"),
            specularBoost: this.gl.getUniformLocation(this.program, "u_specularBoost")
        };
    }

    createProgram(vertexShaderSource, fragmentShaderSource) {
        const vertexShader = this.compileShader(this.gl.VERTEX_SHADER, vertexShaderSource);
        const fragmentShader = this.compileShader(this.gl.FRAGMENT_SHADER, fragmentShaderSource);
        const program = this.gl.createProgram();

        this.gl.attachShader(program, vertexShader);
        this.gl.attachShader(program, fragmentShader);
        this.gl.linkProgram(program);

        if (!this.gl.getProgramParameter(program, this.gl.LINK_STATUS)) {
            throw new Error(this.gl.getProgramInfoLog(program) || "Failed to link WebGL program.");
        }

        return program;
    }

    compileShader(type, source) {
        const shader = this.gl.createShader(type);
        this.gl.shaderSource(shader, source);
        this.gl.compileShader(shader);

        if (!this.gl.getShaderParameter(shader, this.gl.COMPILE_STATUS)) {
            throw new Error(this.gl.getShaderInfoLog(shader) || "Failed to compile WebGL shader.");
        }

        return shader;
    }

    createTexture() {
        const texture = this.gl.createTexture();
        this.gl.bindTexture(this.gl.TEXTURE_2D, texture);
        this.gl.texParameteri(this.gl.TEXTURE_2D, this.gl.TEXTURE_WRAP_S, this.gl.CLAMP_TO_EDGE);
        this.gl.texParameteri(this.gl.TEXTURE_2D, this.gl.TEXTURE_WRAP_T, this.gl.CLAMP_TO_EDGE);
        this.gl.texParameteri(this.gl.TEXTURE_2D, this.gl.TEXTURE_MIN_FILTER, this.gl.LINEAR);
        this.gl.texParameteri(this.gl.TEXTURE_2D, this.gl.TEXTURE_MAG_FILTER, this.gl.LINEAR);
        return texture;
    }

    updateTexture(texture, source) {
        this.gl.bindTexture(this.gl.TEXTURE_2D, texture);
        this.gl.pixelStorei(this.gl.UNPACK_PREMULTIPLY_ALPHA_WEBGL, true);
        this.gl.texImage2D(this.gl.TEXTURE_2D, 0, this.gl.RGBA, this.gl.RGBA, this.gl.UNSIGNED_BYTE, source);
    }

    applyStyleUniforms(styleUniforms) {
        this.gl.uniform3f(this.uniforms.tintColorA, styleUniforms.tintColorA[0], styleUniforms.tintColorA[1], styleUniforms.tintColorA[2]);
        this.gl.uniform3f(this.uniforms.tintColorB, styleUniforms.tintColorB[0], styleUniforms.tintColorB[1], styleUniforms.tintColorB[2]);
        this.gl.uniform1f(this.uniforms.tintAlphaA, styleUniforms.tintAlphaA);
        this.gl.uniform1f(this.uniforms.tintAlphaB, styleUniforms.tintAlphaB);
        this.gl.uniform3f(this.uniforms.rimColor, styleUniforms.rimColor[0], styleUniforms.rimColor[1], styleUniforms.rimColor[2]);
        this.gl.uniform1f(this.uniforms.rimAlpha, styleUniforms.rimAlpha);
        this.gl.uniform3f(this.uniforms.shadowColor, styleUniforms.shadowColor[0], styleUniforms.shadowColor[1], styleUniforms.shadowColor[2]);
        this.gl.uniform1f(this.uniforms.shadowAlpha, styleUniforms.shadowAlpha);
        this.gl.uniform1f(this.uniforms.fillAlpha, styleUniforms.fillAlpha);
        this.gl.uniform1f(this.uniforms.specularBoost, styleUniforms.specularBoost);
    }

    ensureSize(width, height) {
        if (this.canvas.width !== width || this.canvas.height !== height) {
            this.canvas.width = width;
            this.canvas.height = height;
        }

        this.gl.viewport(0, 0, width, height);
    }

    render(instance, sourceCanvas) {
        const width = instance.renderCanvas.width;
        const height = instance.renderCanvas.height;

        if (!width || !height || !instance.renderContext || !instance.displacementCanvas || !instance.specularCanvas) {
            return;
        }

        this.ensureSize(width, height);
        this.gl.useProgram(this.program);

        this.updateTexture(this.sourceTexture, sourceCanvas);
        this.updateTexture(this.displacementTexture, instance.displacementCanvas);
        this.updateTexture(this.specularTexture, instance.specularCanvas);

        this.gl.uniform2f(this.uniforms.resolution, width, height);
        this.gl.uniform1f(this.uniforms.strength, instance._maxDisplacement || 1);
        this.gl.uniform1f(this.uniforms.blur, instance.options.canvasBlur);
        this.gl.uniform1f(this.uniforms.saturation, instance.options.saturate);
        this.gl.uniform1f(this.uniforms.brightness, instance.options.brightness);
        this.gl.uniform1f(this.uniforms.contrast, instance.options.contrast);
        this.applyStyleUniforms(instance.styleUniforms);

        this.gl.activeTexture(this.gl.TEXTURE0);
        this.gl.bindTexture(this.gl.TEXTURE_2D, this.sourceTexture);
        this.gl.uniform1i(this.uniforms.source, 0);

        this.gl.activeTexture(this.gl.TEXTURE1);
        this.gl.bindTexture(this.gl.TEXTURE_2D, this.displacementTexture);
        this.gl.uniform1i(this.uniforms.displacement, 1);

        this.gl.activeTexture(this.gl.TEXTURE2);
        this.gl.bindTexture(this.gl.TEXTURE_2D, this.specularTexture);
        this.gl.uniform1i(this.uniforms.specular, 2);

        this.gl.clearColor(0, 0, 0, 0);
        this.gl.clear(this.gl.COLOR_BUFFER_BIT);
        this.gl.drawArrays(this.gl.TRIANGLE_STRIP, 0, 4);

        instance.renderContext.clearRect(0, 0, width, height);
        instance.renderContext.drawImage(this.canvas, 0, 0, width, height);
    }
}

class LiquidGlassFilter {
    static instances = new Set();
    static imageCache = new Map();
    static syncRaf = null;
    static globalListenersAttached = false;
    static sharedRenderer = null;

    static getSharedRenderer() {
        if (!this.sharedRenderer) {
            this.sharedRenderer = new LiquidGlassRenderer();
        }

        return this.sharedRenderer;
    }

    static attachGlobalListeners() {
        if (this.globalListenersAttached) {
            return;
        }

        const scheduleSync = () => LiquidGlassFilter.scheduleAllSync();
        window.addEventListener("resize", scheduleSync, { passive: true });
        window.addEventListener("scroll", scheduleSync, { passive: true });
        this.globalListenersAttached = true;
    }

    static scheduleAllSync() {
        if (this.syncRaf) {
            return;
        }

        this.syncRaf = window.requestAnimationFrame(() => {
            this.syncRaf = null;
            LiquidGlassFilter.instances.forEach((instance) => {
                if (instance.isVisible !== false) {
                    instance.handleViewportChange();
                }
            });
        });
    }

    static extractFirstUrl(backgroundImage) {
        const match = /url\((['"]?)(.*?)\1\)/.exec(backgroundImage || "");
        return match ? match[2] : "";
    }

    static getCachedImage(src, onLoad) {
        if (!src) {
            return null;
        }

        let entry = LiquidGlassFilter.imageCache.get(src);
        if (!entry) {
            const image = new Image();
            entry = {
                image,
                loaded: false,
                error: false,
                callbacks: []
            };

            image.onload = () => {
                entry.loaded = true;
                entry.callbacks.splice(0).forEach((callback) => callback(image));
            };

            image.onerror = () => {
                entry.error = true;
                entry.callbacks.length = 0;
            };

            image.src = src;
            LiquidGlassFilter.imageCache.set(src, entry);
        }

        if (entry.loaded) {
            return entry.image;
        }

        if (!entry.error && onLoad) {
            entry.callbacks.push(onLoad);
        }

        return null;
    }

    static parseSizeToken(token, containerSize) {
        if (!token || token === "auto") {
            return null;
        }

        if (token.endsWith("%")) {
            return (parseFloat(token) / 100) * containerSize;
        }

        if (token.endsWith("px")) {
            return parseFloat(token);
        }

        const numericValue = parseFloat(token);
        return Number.isFinite(numericValue) ? numericValue : null;
    }

    static parsePositionToken(token, freeSpace, axis) {
        const normalized = (token || "50%").trim().toLowerCase();

        if (normalized === "center") {
            return freeSpace / 2;
        }

        if (axis === "x") {
            if (normalized === "left") {
                return 0;
            }

            if (normalized === "right") {
                return freeSpace;
            }
        }

        if (axis === "y") {
            if (normalized === "top") {
                return 0;
            }

            if (normalized === "bottom") {
                return freeSpace;
            }
        }

        if (normalized.endsWith("%")) {
            return (parseFloat(normalized) / 100) * freeSpace;
        }

        if (normalized.endsWith("px")) {
            return parseFloat(normalized);
        }

        const numericValue = parseFloat(normalized);
        return Number.isFinite(numericValue) ? numericValue : freeSpace / 2;
    }

    static computeBackgroundDrawRect(styles, containerWidth, containerHeight, imageWidth, imageHeight) {
        const backgroundSize = (styles.backgroundSize || "auto").trim();
        let drawWidth = imageWidth;
        let drawHeight = imageHeight;

        if (backgroundSize === "cover") {
            const scale = Math.max(containerWidth / imageWidth, containerHeight / imageHeight);
            drawWidth = imageWidth * scale;
            drawHeight = imageHeight * scale;
        } else if (backgroundSize === "contain") {
            const scale = Math.min(containerWidth / imageWidth, containerHeight / imageHeight);
            drawWidth = imageWidth * scale;
            drawHeight = imageHeight * scale;
        } else if (backgroundSize !== "auto") {
            const [rawWidth, rawHeight = "auto"] = backgroundSize.split(/\s+/);
            const parsedWidth = LiquidGlassFilter.parseSizeToken(rawWidth, containerWidth);
            const parsedHeight = LiquidGlassFilter.parseSizeToken(rawHeight, containerHeight);

            if (parsedWidth !== null && parsedHeight !== null) {
                drawWidth = parsedWidth;
                drawHeight = parsedHeight;
            } else if (parsedWidth !== null) {
                drawWidth = parsedWidth;
                drawHeight = imageHeight * (parsedWidth / imageWidth);
            } else if (parsedHeight !== null) {
                drawHeight = parsedHeight;
                drawWidth = imageWidth * (parsedHeight / imageHeight);
            }
        }

        const backgroundPosition = (styles.backgroundPosition || "50% 50%").trim();
        const [posXToken, posYToken = posXToken] = backgroundPosition.split(/\s+/);
        const x = LiquidGlassFilter.parsePositionToken(posXToken, containerWidth - drawWidth, "x");
        const y = LiquidGlassFilter.parsePositionToken(posYToken, containerHeight - drawHeight, "y");

        return { x, y, width: drawWidth, height: drawHeight };
    }

    static computeObjectFitDrawRect(styles, boxWidth, boxHeight, imageWidth, imageHeight) {
        const fit = (styles.objectFit || "fill").trim().toLowerCase();
        let drawWidth = boxWidth;
        let drawHeight = boxHeight;

        if (fit === "contain") {
            const scale = Math.min(boxWidth / imageWidth, boxHeight / imageHeight);
            drawWidth = imageWidth * scale;
            drawHeight = imageHeight * scale;
        } else if (fit === "cover") {
            const scale = Math.max(boxWidth / imageWidth, boxHeight / imageHeight);
            drawWidth = imageWidth * scale;
            drawHeight = imageHeight * scale;
        } else if (fit === "none") {
            drawWidth = imageWidth;
            drawHeight = imageHeight;
        } else if (fit === "scale-down") {
            const containScale = Math.min(boxWidth / imageWidth, boxHeight / imageHeight, 1);
            drawWidth = imageWidth * containScale;
            drawHeight = imageHeight * containScale;
        }

        const objectPosition = (styles.objectPosition || "50% 50%").trim();
        const [posXToken, posYToken = posXToken] = objectPosition.split(/\s+/);
        const x = LiquidGlassFilter.parsePositionToken(posXToken, boxWidth - drawWidth, "x");
        const y = LiquidGlassFilter.parsePositionToken(posYToken, boxHeight - drawHeight, "y");

        return { x, y, width: drawWidth, height: drawHeight };
    }

    constructor(element, options = {}) {
        this.element = element;
        this.sourceSelector = options.sourceSelector || element.dataset.glassSource || "";
        this.mode = "webgl";
        this.isVisible = true;
        this.options = {
            surfaceType: options.surfaceType || "convex_squircle",
            bezelWidth: options.bezelWidth || 30,
            glassThickness: options.glassThickness || 100,
            refractiveIndex: options.refractiveIndex || 1.5,
            refractionScale: options.refractionScale || 1.1,
            specularOpacity: options.specularOpacity || 0.6,
            canvasBlur: options.canvasBlur || 1.1,
            saturate: options.saturate || 1.14,
            brightness: options.brightness || 1.1,
            contrast: options.contrast || 1.02,
            edgeRadius: options.edgeRadius || 24,
            transitionSyncDuration: options.transitionSyncDuration || 700,
            ...options
        };

        this.buildRaf = null;
        this.renderRaf = null;
        this.sourceMutationObserver = null;

        try {
            this.setupLayers();
            this.setupWebGL();
            this.refreshSourceElement();

            this.element._liquidGlassInstance = this;
            LiquidGlassFilter.instances.add(this);
            LiquidGlassFilter.attachGlobalListeners();

            this.buildWebGLAssets();
            this.renderWebGL();
            this.setupObservers();
        } catch (error) {
            console.error("Liquid glass initialization failed.", error);
            this.disableEnhancement();
        }
    }

    setVisibility(isVisible) {
        this.isVisible = isVisible;
        if (isVisible) {
            this.scheduleBuild();
        }
    }

    resolveFromSelector(selector) {
        const normalized = (selector || "").trim();
        if (!normalized) {
            return null;
        }

        const matches = [];
        const parent = this.element.parentElement;
        if (parent) {
            const closest = parent.closest(normalized);
            if (closest && closest !== this.element) {
                matches.push(closest);
            }
        }

        const nearestSection = this.element.closest("section, header, footer, main, article, body");
        if (nearestSection && typeof nearestSection.querySelectorAll === "function") {
            matches.push(...nearestSection.querySelectorAll(normalized));
        }

        matches.push(...document.querySelectorAll(normalized));

        for (const match of matches) {
            if (match && match !== this.element) {
                return match;
            }
        }

        return null;
    }

    refreshSourceElement() {
        const selectors = [];
        if (this.sourceSelector) {
            selectors.push(this.sourceSelector);
        }
        selectors.push(".liquid-scene", ".hero-slide.is-active, .hero, #hero-slider, .legal-content, .site-footer");

        let nextSource = null;
        for (const selector of selectors) {
            nextSource = this.resolveFromSelector(selector);
            if (nextSource) {
                break;
            }
        }

        if (nextSource === this.sourceElement) {
            return;
        }

        this.sourceElement = nextSource;

        if (this.sourceMutationObserver) {
            this.sourceMutationObserver.disconnect();
            this.sourceMutationObserver = null;
        }

        if (!this.sourceElement) {
            return;
        }

        this.sourceMutationObserver = new MutationObserver(() => {
            if (this.isVisible !== false) {
                this.scheduleRender();
            }
        });

        this.sourceMutationObserver.observe(this.sourceElement, {
            attributes: true,
            attributeFilter: ["class", "style", "hidden"]
        });

        if (this.sourceElement instanceof HTMLImageElement && !this.sourceElement.complete) {
            this.sourceElement.addEventListener("load", () => this.scheduleBuild(), { once: true });
        }
    }

    setupLayers() {
        this.element.classList.add("liquid-enhanced");

        this.backdropLayer = document.createElement("span");
        this.backdropLayer.className = "liquid-backdrop-layer";
        this.backdropLayer.setAttribute("aria-hidden", "true");
        this.element.insertBefore(this.backdropLayer, this.element.firstChild);
    }

    setupWebGL() {
        this.renderCanvas = document.createElement("canvas");
        this.renderCanvas.className = "liquid-render-surface";
        this.renderCanvas.setAttribute("aria-hidden", "true");
        this.backdropLayer.appendChild(this.renderCanvas);
        this.renderContext = this.renderCanvas.getContext("2d");
        if (!this.renderContext) {
            throw new Error("2D canvas context is unavailable.");
        }
        this.renderContext.imageSmoothingEnabled = true;
        this.renderContext.imageSmoothingQuality = "high";

        this.captureCanvas = document.createElement("canvas");
        this.captureContext = this.captureCanvas.getContext("2d");
        if (!this.captureContext) {
            throw new Error("2D canvas context is unavailable.");
        }
        this.captureContext.imageSmoothingEnabled = true;
        this.captureContext.imageSmoothingQuality = "high";
        this.renderer = LiquidGlassFilter.getSharedRenderer();
    }

    setupObservers() {
        this.resizeObserver = new ResizeObserver(() => {
            if (this.isVisible !== false) {
                this.scheduleBuild();
            }
        });

        this.resizeObserver.observe(this.element);

        this.mutationObserver = new MutationObserver(() => {
            if (this.isVisible !== false) {
                this.scheduleRender();
            }
        });

        this.mutationObserver.observe(this.element, {
            attributes: true,
            attributeFilter: ["class", "style", "hidden"]
        });
    }

    handleViewportChange() {
        this.scheduleRender();
    }

    scheduleBuild() {
        if (this.buildRaf) {
            return;
        }

        this.buildRaf = window.requestAnimationFrame(() => {
            this.buildRaf = null;
            this.buildWebGLAssets();
            this.renderWebGL();
        });
    }

    scheduleRender() {
        if (this.renderRaf) {
            return;
        }

        this.renderRaf = window.requestAnimationFrame(() => {
            this.renderRaf = null;
            this.renderWebGL();
        });
    }

    measureElement() {
        const rect = this.element.getBoundingClientRect();
        return {
            width: Math.max(10, Math.round(rect.width)),
            height: Math.max(10, Math.round(rect.height))
        };
    }

    syncStyleUniforms() {
        const styles = window.getComputedStyle(this.element);
        this.styleUniforms = {
            tintColorA: parseRGBTriplet(styles.getPropertyValue("--liquid-tint-rgb-a"), [255, 255, 255]).map((value) => value / 255),
            tintColorB: parseRGBTriplet(styles.getPropertyValue("--liquid-tint-rgb-b"), [255, 255, 255]).map((value) => value / 255),
            tintAlphaA: safeNumber(styles.getPropertyValue("--liquid-tint-alpha-a"), 0.24),
            tintAlphaB: safeNumber(styles.getPropertyValue("--liquid-tint-alpha-b"), 0.08),
            rimColor: parseRGBTriplet(styles.getPropertyValue("--liquid-rim-rgb"), [255, 255, 255]).map((value) => value / 255),
            rimAlpha: safeNumber(styles.getPropertyValue("--liquid-rim-alpha"), 0.46),
            shadowColor: parseRGBTriplet(styles.getPropertyValue("--liquid-shadow-rgb"), [7, 21, 32]).map((value) => value / 255),
            shadowAlpha: safeNumber(styles.getPropertyValue("--liquid-shadow-alpha"), 0.18),
            fillAlpha: safeNumber(styles.getPropertyValue("--liquid-fill-alpha"), 0.96),
            specularBoost: safeNumber(styles.getPropertyValue("--liquid-specular-boost"), 0.85)
        };

        return this.styleUniforms;
    }

    buildWebGLAssets() {
        this.refreshSourceElement();

        const { width, height } = this.measureElement();
        if (width <= 10 && height <= 10) {
            return;
        }

        if (this.renderCanvas.width !== width || this.renderCanvas.height !== height) {
            this.renderCanvas.width = width;
            this.renderCanvas.height = height;
            this.captureCanvas.width = width;
            this.captureCanvas.height = height;
        } else {
            this.captureContext.clearRect(0, 0, width, height);
        }

        const precomputed1D = this.calculateDisplacementMap1D();
        this.displacementCanvas = this.calculateDisplacementCanvas(width, height, precomputed1D);
        this.specularCanvas = this.calculateSpecularCanvas(width, height);
        this.syncStyleUniforms();
    }

    getSourceDescriptor() {
        this.refreshSourceElement();

        if (!this.sourceElement || !this.sourceElement.isConnected) {
            return null;
        }

        if (this.sourceElement.classList.contains("liquid-panel")) {
            return {
                type: "liquid",
                element: this.sourceElement
            };
        }

        if (this.sourceElement instanceof HTMLImageElement) {
            return {
                type: "image",
                element: this.sourceElement
            };
        }

        const styles = window.getComputedStyle(this.sourceElement);
        const hasBackgroundImage = styles.backgroundImage && styles.backgroundImage !== "none";
        const hasBackgroundColor = !isTransparentColor(styles.backgroundColor);

        if (hasBackgroundImage || hasBackgroundColor) {
            return {
                type: "background",
                element: this.sourceElement,
                styles
            };
        }

        return null;
    }

    captureSourceToCanvas() {
        if (!this.captureContext) {
            return null;
        }

        const descriptor = this.getSourceDescriptor();
        if (!descriptor) {
            return null;
        }

        const { width, height } = this.measureElement();
        this.captureContext.clearRect(0, 0, width, height);

        if (descriptor.type === "background") {
            return this.drawBackgroundSource(descriptor) ? this.captureCanvas : null;
        }

        if (descriptor.type === "image") {
            return this.drawImageSource(descriptor.element) ? this.captureCanvas : null;
        }

        if (descriptor.type === "liquid") {
            return this.drawLiquidSource(descriptor.element) ? this.captureCanvas : null;
        }

        return null;
    }

    drawBackgroundSource(descriptor) {
        const sourceRect = descriptor.element.getBoundingClientRect();
        const targetRect = this.element.getBoundingClientRect();
        const backgroundColor = descriptor.styles.backgroundColor;

        if (backgroundColor && backgroundColor !== "rgba(0, 0, 0, 0)" && backgroundColor !== "transparent") {
            this.captureContext.fillStyle = backgroundColor;
            this.captureContext.fillRect(
                sourceRect.left - targetRect.left,
                sourceRect.top - targetRect.top,
                sourceRect.width,
                sourceRect.height
            );
        }

        const backgroundUrl = LiquidGlassFilter.extractFirstUrl(descriptor.styles.backgroundImage);
        if (!backgroundUrl) {
            return true;
        }

        const image = LiquidGlassFilter.getCachedImage(backgroundUrl, () => this.scheduleRender());
        if (!image) {
            return false;
        }

        const drawRect = LiquidGlassFilter.computeBackgroundDrawRect(
            descriptor.styles,
            sourceRect.width,
            sourceRect.height,
            image.naturalWidth,
            image.naturalHeight
        );

        this.captureContext.drawImage(
            image,
            (sourceRect.left - targetRect.left) + drawRect.x,
            (sourceRect.top - targetRect.top) + drawRect.y,
            drawRect.width,
            drawRect.height
        );

        return true;
    }

    drawImageSource(sourceImage) {
        if (!sourceImage.complete || !sourceImage.naturalWidth) {
            sourceImage.addEventListener("load", () => this.scheduleRender(), { once: true });
            return false;
        }

        const sourceRect = sourceImage.getBoundingClientRect();
        const targetRect = this.element.getBoundingClientRect();
        const styles = window.getComputedStyle(sourceImage);
        const drawRect = LiquidGlassFilter.computeObjectFitDrawRect(
            styles,
            sourceRect.width,
            sourceRect.height,
            sourceImage.naturalWidth,
            sourceImage.naturalHeight
        );
        const left = sourceRect.left - targetRect.left;
        const top = sourceRect.top - targetRect.top;

        this.captureContext.save();
        this.captureContext.beginPath();
        this.captureContext.rect(left, top, sourceRect.width, sourceRect.height);
        this.captureContext.clip();
        this.captureContext.drawImage(
            sourceImage,
            left + drawRect.x,
            top + drawRect.y,
            drawRect.width,
            drawRect.height
        );
        this.captureContext.restore();

        return true;
    }

    drawLiquidSource(sourcePanel) {
        const sourceInstance = sourcePanel._liquidGlassInstance;
        if (!sourceInstance || sourceInstance === this) {
            return false;
        }

        const sourceCanvas = sourceInstance.renderCanvas;
        if (!sourceCanvas) {
            return false;
        }

        const sourceRect = sourcePanel.getBoundingClientRect();
        const targetRect = this.element.getBoundingClientRect();

        this.captureContext.drawImage(
            sourceCanvas,
            sourceRect.left - targetRect.left,
            sourceRect.top - targetRect.top,
            sourceRect.width,
            sourceRect.height
        );

        return true;
    }

    renderWebGL() {
        if (this.mode !== "webgl" || !this.renderer || this.isVisible === false) {
            return;
        }

        const sourceCanvas = this.captureSourceToCanvas();
        if (!sourceCanvas) {
            return;
        }

        this.syncStyleUniforms();
        this.renderer.render(this, sourceCanvas);
    }

    calculateDisplacementMap1D(samples = 128) {
        const eta = 1 / this.options.refractiveIndex;
        const surfaceFn = MathUtils[this.options.surfaceType];

        function refract(normalX, normalY) {
            const dot = normalY;
            const k = 1 - eta * eta * (1 - dot * dot);
            if (k < 0) {
                return null;
            }

            const kSqrt = Math.sqrt(k);
            return [
                -(eta * dot + kSqrt) * normalX,
                eta - (eta * dot + kSqrt) * normalY
            ];
        }

        const result = [];

        for (let i = 0; i < samples; i++) {
            const x = i / samples;
            const y = surfaceFn(x);
            const dx = x < 1 ? 0.0001 : -0.0001;
            const y2 = surfaceFn(Math.max(0, Math.min(1, x + dx)));
            const derivative = (y2 - y) / dx;
            const magnitude = Math.sqrt(derivative * derivative + 1);
            const normal = [-derivative / magnitude, -1 / magnitude];
            const refracted = refract(normal[0], normal[1]);

            if (!refracted) {
                result.push(0);
                continue;
            }

            const remainingHeightOnBezel = y * this.options.bezelWidth;
            const remainingHeight = remainingHeightOnBezel + this.options.glassThickness;
            result.push(refracted[0] * (remainingHeight / refracted[1]));
        }

        return result;
    }

    calculateDisplacementCanvas(width, height, precomputed1D) {
        const imageData = new ImageData(width, height);
        const radius = this.options.edgeRadius;
        const bezelWidth = this.options.bezelWidth;
        const maximumDisplacement = Math.max(...precomputed1D.map(Math.abs)) || 1;
        this._maxDisplacement = maximumDisplacement * this.options.refractionScale || 1;

        for (let i = 0; i < imageData.data.length; i += 4) {
            imageData.data[i] = 128;
            imageData.data[i + 1] = 128;
            imageData.data[i + 2] = 0;
            imageData.data[i + 3] = 255;
        }

        const r2 = radius * radius;
        const rPlus1Sq = (radius + 1) * (radius + 1);
        const rMinusBezelSq = Math.max(0, (radius - bezelWidth) * (radius - bezelWidth));
        const centerW = width - radius * 2;
        const centerH = height - radius * 2;

        for (let y1 = 0; y1 < height; y1++) {
            for (let x1 = 0; x1 < width; x1++) {
                const idx = (y1 * width + x1) * 4;
                const onLeft = x1 < radius;
                const onRight = x1 >= width - radius;
                const onTop = y1 < radius;
                const onBottom = y1 >= height - radius;
                const x = onLeft ? x1 - radius : (onRight ? x1 - radius - centerW : 0);
                const y = onTop ? y1 - radius : (onBottom ? y1 - radius - centerH : 0);
                const distSq = x * x + y * y;
                const isInBezel = distSq <= rPlus1Sq && distSq >= rMinusBezelSq;

                if (!isInBezel) {
                    continue;
                }

                const distToCenter = Math.sqrt(distSq);
                const opacity = distSq < r2 ? 1 : 1 - (distToCenter - Math.sqrt(r2)) / (Math.sqrt(rPlus1Sq) - Math.sqrt(r2));
                const distFromSide = radius - distToCenter;
                const cos = distToCenter > 0 ? x / distToCenter : 0;
                const sin = distToCenter > 0 ? y / distToCenter : 0;
                const bezelRatio = Math.max(0, Math.min(1, distFromSide / bezelWidth));
                const bezelIdx = Math.floor(bezelRatio * precomputed1D.length);
                const safeIdx = Math.max(0, Math.min(bezelIdx, precomputed1D.length - 1));
                const displacementMag = precomputed1D[safeIdx] || 0;
                const dX = (-cos * displacementMag) / maximumDisplacement;
                const dY = (-sin * displacementMag) / maximumDisplacement;

                imageData.data[idx] = Math.max(0, Math.min(255, 128 + dX * 127 * opacity));
                imageData.data[idx + 1] = Math.max(0, Math.min(255, 128 + dY * 127 * opacity));
                imageData.data[idx + 2] = 0;
                imageData.data[idx + 3] = 255;
            }
        }

        return this.imageDataToCanvas(imageData);
    }

    calculateSpecularCanvas(width, height) {
        const imageData = new ImageData(width, height);
        const radius = this.options.edgeRadius;
        const specularAngle = Math.PI * 1.25;
        const specVec = [Math.cos(specularAngle), Math.sin(specularAngle)];
        const specThickness = 2.0;
        const r2 = radius * radius;
        const rPlus1Sq = (radius + 1) * (radius + 1);
        const rMinusSpecSq = Math.max(0, (radius - specThickness) * (radius - specThickness));
        const centerW = width - radius * 2;
        const centerH = height - radius * 2;

        for (let y1 = 0; y1 < height; y1++) {
            for (let x1 = 0; x1 < width; x1++) {
                const idx = (y1 * width + x1) * 4;
                const onLeft = x1 < radius;
                const onRight = x1 >= width - radius;
                const onTop = y1 < radius;
                const onBottom = y1 >= height - radius;
                const x = onLeft ? x1 - radius : (onRight ? x1 - radius - centerW : 0);
                const y = onTop ? y1 - radius : (onBottom ? y1 - radius - centerH : 0);
                const distSq = x * x + y * y;
                const isNearEdge = distSq <= rPlus1Sq && distSq >= rMinusSpecSq;

                if (!isNearEdge) {
                    continue;
                }

                const distToCenter = Math.sqrt(distSq);
                const distFromSide = radius - distToCenter;
                const opacity = distSq < r2 ? 1 : 1 - (distToCenter - Math.sqrt(r2)) / (Math.sqrt(rPlus1Sq) - Math.sqrt(r2));
                const cos = distToCenter > 0 ? x / distToCenter : 0;
                const sin = distToCenter > 0 ? -y / distToCenter : 0;
                const dot = Math.max(0, cos * specVec[0] + sin * specVec[1]);
                const edgeRatio = Math.max(0, Math.min(1, distFromSide / specThickness));
                const sharpFalloff = Math.sqrt(1 - (1 - edgeRatio) * (1 - edgeRatio));
                const coeff = dot * sharpFalloff;
                const color = Math.min(255, 255 * coeff);
                const finalOpacity = Math.min(255, color * coeff * opacity * this.options.specularOpacity);

                imageData.data[idx] = color;
                imageData.data[idx + 1] = color;
                imageData.data[idx + 2] = color;
                imageData.data[idx + 3] = finalOpacity;
            }
        }

        return this.imageDataToCanvas(imageData);
    }

    imageDataToCanvas(imageData) {
        const canvas = document.createElement("canvas");
        canvas.width = imageData.width;
        canvas.height = imageData.height;

        const context = canvas.getContext("2d");
        if (!context) {
            throw new Error("2D canvas context is unavailable.");
        }

        context.putImageData(imageData, 0, 0);
        return canvas;
    }

    disableEnhancement() {
        LiquidGlassFilter.instances.delete(this);

        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
        }

        if (this.mutationObserver) {
            this.mutationObserver.disconnect();
        }

        if (this.sourceMutationObserver) {
            this.sourceMutationObserver.disconnect();
        }

        if (this.buildRaf) {
            window.cancelAnimationFrame(this.buildRaf);
        }

        if (this.renderRaf) {
            window.cancelAnimationFrame(this.renderRaf);
        }

        [this.backdropLayer].forEach((layer) => {
            if (layer && layer.parentNode === this.element) {
                layer.remove();
            }
        });

        if (this.element) {
            this.element.classList.remove("liquid-enhanced");
            delete this.element._liquidGlassInstance;
        }
    }
}

const LIQUID_SCENE_RULES = [
    {
        selector: ".hero, #hero-slider, .slider-section, .how-it-works, .center-cta, .final-cta-section, .immobilien-calc-v2, .section-finanzwelt, .site-footer",
        tone: "dark"
    },
    {
        selector: ".pillars, .why-finora, .testimonials, .faq, .legal-content, .content-section--white, .content-section--light, .content-section--gray",
        tone: "light"
    }
];

const LIQUID_PANEL_RULES = [
    {
        selector: ".site-header",
        profile: "chrome",
        source: ".hero-slide.is-active, .hero, #hero-slider, .legal-content, .liquid-scene"
    },
    {
        selector: ".header-lang-btn, .mobile-menu-toggle",
        profile: "button",
        source: ".liquid-panel"
    },
    {
        selector: ".btn, .hero-slider-arrow, .fs-nav-btn",
        profile: "button",
        source: ".liquid-scene"
    },
    {
        selector: ".hero-badge, .testimonials-nav-dot, .fs-item, .tab-nav button, .btn.btn-audience",
        profile: "chip",
        source: ".liquid-scene"
    },
    {
        selector: ".header-lang-dropdown, .nav-menu .sub-menu, .mobile-menu",
        profile: "menu",
        source: ".hero-slide.is-active, .hero, #hero-slider, .liquid-scene"
    },
    {
        selector: ".hero-services .service-item, .pillar-card, .audience-card:not([aria-hidden=\"true\"]), .fs-card, .testimonial-card, .accordion-item, .timeline-item__card, .contact-form-card, .kontakt-info-card, .tab-panel",
        profile: "card",
        source: ".liquid-scene"
    },
    {
        selector: ".calc-v2__card, .calc-v2__kpi-strip",
        profile: "metric",
        source: ".liquid-scene"
    },
    {
        selector: ".approach-tile__front, .approach-tile__back, .flip-card-front, .flip-card-back, .flip-box-front, .flip-box-back",
        profile: "card",
        source: ".liquid-scene"
    },
    {
        selector: ".footer-main",
        profile: "chrome",
        source: ".site-footer"
    },
    {
        selector: ".footer-contact .contact-item",
        profile: "chip",
        source: ".site-footer"
    },
    {
        selector: ".legal-body",
        profile: "legal",
        source: ".legal-content"
    }
];

const LIQUID_PROFILE_PRESETS = {
    chrome: {
        surfaceType: "convex_squircle",
        bezelWidth: 18,
        glassThickness: 34,
        refractionScale: 0.72,
        specularOpacity: 0.78,
        canvasBlur: 1.4,
        saturate: 1.12,
        brightness: 1.04,
        contrast: 1.04
    },
    menu: {
        surfaceType: "convex_squircle",
        bezelWidth: 20,
        glassThickness: 40,
        refractionScale: 0.8,
        specularOpacity: 0.82,
        canvasBlur: 1.5,
        saturate: 1.14,
        brightness: 1.05,
        contrast: 1.05
    },
    card: {
        surfaceType: "convex_squircle",
        bezelWidth: 24,
        glassThickness: 48,
        refractionScale: 0.92,
        specularOpacity: 0.72,
        canvasBlur: 2.0,
        saturate: 1.18,
        brightness: 1.06,
        contrast: 1.05
    },
    button: {
        surfaceType: "lip",
        bezelWidth: 12,
        glassThickness: 22,
        refractionScale: 0.56,
        specularOpacity: 0.92,
        canvasBlur: 1.2,
        saturate: 1.12,
        brightness: 1.05,
        contrast: 1.04
    },
    chip: {
        surfaceType: "lip",
        bezelWidth: 10,
        glassThickness: 18,
        refractionScale: 0.5,
        specularOpacity: 0.88,
        canvasBlur: 1.1,
        saturate: 1.1,
        brightness: 1.04,
        contrast: 1.03
    },
    metric: {
        surfaceType: "convex_squircle",
        bezelWidth: 22,
        glassThickness: 44,
        refractionScale: 0.84,
        specularOpacity: 0.7,
        canvasBlur: 1.8,
        saturate: 1.16,
        brightness: 1.04,
        contrast: 1.05
    },
    legal: {
        surfaceType: "convex_squircle",
        bezelWidth: 20,
        glassThickness: 38,
        refractionScale: 0.62,
        specularOpacity: 0.66,
        canvasBlur: 1.25,
        saturate: 1.04,
        brightness: 1.02,
        contrast: 1.02
    }
};

function parseRGBTriplet(value, fallback) {
    const normalized = (value || "").trim().replace(/,/g, " ");
    const parts = normalized.split(/\s+/).filter(Boolean).map(Number);
    if (parts.length >= 3 && parts.every((part) => Number.isFinite(part))) {
        return parts.slice(0, 3);
    }
    return fallback;
}

function queryWithin(root, selector) {
    if (!root || !selector) {
        return [];
    }

    const result = [];
    if (root.nodeType === 1 && root.matches(selector)) {
        result.push(root);
    }

    if (typeof root.querySelectorAll === "function") {
        result.push(...root.querySelectorAll(selector));
    }

    return result;
}

function safeNumber(value, fallback) {
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : fallback;
}

function isTransparentColor(value) {
    const normalized = (value || "").trim().toLowerCase();

    if (!normalized || normalized === "transparent" || normalized === "rgba(0, 0, 0, 0)" || normalized === "rgb(0 0 0 / 0)") {
        return true;
    }

    const legacyAlphaMatch = normalized.match(/^rgba\((.+)\)$/);
    if (legacyAlphaMatch) {
        const parts = legacyAlphaMatch[1].split(",").map((part) => part.trim());
        if (parts.length === 4 && parseFloat(parts[3]) === 0) {
            return true;
        }
    }

    const modernAlphaMatch = normalized.match(/\/\s*([0-9.]+)%?\s*\)$/);
    if (modernAlphaMatch) {
        const rawAlpha = modernAlphaMatch[1];
        const alpha = normalized.includes("%)") ? parseFloat(rawAlpha) / 100 : parseFloat(rawAlpha);
        if (alpha === 0) {
            return true;
        }
    }

    return false;
}

const FinoraLiquidGlass = {
    started: false,
    panelObserver: null,
    mutationObserver: null,

    supports() {
        try {
            const canvas = document.createElement("canvas");
            return !!(canvas.getContext("webgl") || canvas.getContext("experimental-webgl"));
        } catch (error) {
            return false;
        }
    },

    computeRadius(element, profile) {
        const computed = window.getComputedStyle(element);
        const radius = safeNumber(computed.borderTopLeftRadius, 24);

        if (profile === "button" && radius < 10) {
            return 12;
        }

        if (profile === "chip" && radius < 10) {
            return 14;
        }

        return Math.max(10, Math.min(42, radius || 24));
    },

    annotateScenes(root) {
        LIQUID_SCENE_RULES.forEach((rule) => {
            queryWithin(root, rule.selector).forEach((element) => {
                if (!(element instanceof HTMLElement)) {
                    return;
                }

                if (!element.dataset.liquidTone) {
                    element.dataset.liquidTone = rule.tone;
                }

                if (element.dataset.liquidTone !== rule.tone && element.classList.contains("liquid-scene")) {
                    return;
                }

                element.classList.add("liquid-scene", `liquid-scene--${rule.tone}`);

                const computed = window.getComputedStyle(element);
                if (!(computed.backgroundImage || "").includes("url(")) {
                    element.classList.add("liquid-scene--textured");
                }
            });
        });
    },

    annotatePanels(root) {
        LIQUID_PANEL_RULES.forEach((rule) => {
            queryWithin(root, rule.selector).forEach((element) => {
                if (!(element instanceof HTMLElement) || element.dataset.liquidSkip === "true") {
                    return;
                }

                element.classList.add("liquid-panel");
                if (rule.profile === "button" || rule.profile === "chip") {
                    element.classList.add("liquid-button");
                }

                if (!element.dataset.glassProfile) {
                    element.dataset.glassProfile = rule.profile;
                }

                if (!element.dataset.glassSource) {
                    element.dataset.glassSource = rule.source || ".liquid-scene";
                }
            });
        });
    },

    initPanel(element) {
        if (!(element instanceof HTMLElement) || element._liquidGlassInstance || element.dataset.liquidFailed === "true") {
            return;
        }

        const profile = element.dataset.glassProfile || "card";
        const preset = LIQUID_PROFILE_PRESETS[profile] || LIQUID_PROFILE_PRESETS.card;

        try {
            new LiquidGlassFilter(element, {
                ...preset,
                sourceSelector: element.dataset.glassSource || ".liquid-scene",
                refractiveIndex: 1.5,
                edgeRadius: this.computeRadius(element, profile)
            });
        } catch (error) {
            element.dataset.liquidFailed = "true";
            console.error("Liquid glass initialization failed.", error);
        }
    },

    observePanels(root) {
        queryWithin(root, ".liquid-panel").forEach((panel) => {
            if (panel instanceof HTMLElement) {
                this.panelObserver.observe(panel);
            }
        });
    },

    refresh(root = document) {
        if (!this.started || !this.panelObserver) {
            return;
        }

        this.annotateScenes(root);
        this.annotatePanels(root);
        this.observePanels(root);
        LiquidGlassFilter.scheduleAllSync();
    },

    start() {
        if (this.started) {
            this.refresh(document);
            return;
        }

        if (!this.supports()) {
            return;
        }

        this.started = true;
        document.documentElement.classList.add("webgl-liquid-glass");

        this.panelObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                const element = entry.target;
                if (!(element instanceof HTMLElement)) {
                    return;
                }

                if (entry.isIntersecting) {
                    this.initPanel(element);
                }

                if (element._liquidGlassInstance) {
                    element._liquidGlassInstance.setVisibility(entry.isIntersecting);
                }
            });
        }, {
            rootMargin: "180px 0px 180px 0px",
            threshold: 0
        });

        this.annotateScenes(document);
        this.annotatePanels(document);
        this.observePanels(document);
        LiquidGlassFilter.scheduleAllSync();

        this.mutationObserver = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node instanceof HTMLElement) {
                        this.refresh(node);
                    }
                });
            });
        });

        this.mutationObserver.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
};

window.LiquidGlassFilter = LiquidGlassFilter;
window.FinoraLiquidGlass = FinoraLiquidGlass;

document.addEventListener("DOMContentLoaded", () => {
    FinoraLiquidGlass.start();
});
