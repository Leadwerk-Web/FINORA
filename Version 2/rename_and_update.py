import os
import re

base_dir = r"C:\Users\mars\OneDrive\Documents\GitHub\FINORA\Version 2"
images_dir = os.path.join(base_dir, "assets", "images")

renames = {} # old_name -> new_name
rename_paths = [] # list of (old_path, new_path)

# 1. Gather all renaming actions
for root, dirs, files in os.walk(images_dir):
    for f in files:
        if 'x' in f.lower() and f.endswith('.jpg'):
            name_no_ext = os.path.splitext(f)[0]
            new_name = name_no_ext.lstrip('_')
            parts = re.split(r'[_\-]', new_name)
            new_name = parts[0] + '.jpg'
            
            if new_name != f:
                renames[f] = new_name
                old_path = os.path.join(root, f)
                new_path = os.path.join(root, new_name)
                rename_paths.append((old_path, new_path))

print(f"Found {len(renames)} files to rename.")

# Sort renames by length descending to prevent partial replacement issues
sorted_renames = sorted(renames.items(), key=lambda item: len(item[0]), reverse=True)

# 2. Update files
files_updated = 0
for root, dirs, files in os.walk(base_dir):
    # skip .git or node_modules if any
    if '.git' in root or 'node_modules' in root:
        continue
    for f in files:
        if f.endswith(('.html', '.css', '.js', '.php')):
            filepath = os.path.join(root, f)
            try:
                with open(filepath, 'r', encoding='utf-8') as file:
                    content = file.read()
                
                new_content = content
                for old_name, new_name in sorted_renames:
                    new_content = new_content.replace(old_name, new_name)
                
                if new_content != content:
                    with open(filepath, 'w', encoding='utf-8') as file:
                        file.write(new_content)
                    files_updated += 1
                    print(f"Updated references in: {f}")
            except Exception as e:
                print(f"Could not process {filepath}: {e}")

print(f"Updated {files_updated} files with new names.")

# 3. Rename files in filesystem
renamed_count = 0
for old_path, new_path in rename_paths:
    if os.path.exists(old_path):
        if os.path.exists(new_path) and old_path != new_path:
            # duplicate exists, safe to remove original to avoid clutter
            print(f"Duplicate exists for {new_path}, removing {old_path}")
            os.remove(old_path)
            renamed_count += 1
        else:
            print(f"Renaming {old_path} to {new_path}")
            os.rename(old_path, new_path)
            renamed_count += 1

print(f"Renamed/Removed {renamed_count} image files.")
