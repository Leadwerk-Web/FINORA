import os
import re

base_dir = r"C:\Users\mars\OneDrive\Documents\GitHub\FINORA\Version 2"
images_dir = os.path.join(base_dir, "assets", "images")

renames = {}

for root, dirs, files in os.walk(images_dir):
    for f in files:
        if 'x' in f.lower() and f.endswith('.jpg'):
            name_no_ext = os.path.splitext(f)[0]
            new_name = name_no_ext.lstrip('_')
            parts = re.split(r'[_\-]', new_name)
            new_name = parts[0] + '.jpg'
            
            if new_name != f:
                renames[f] = new_name
                print(f"{f} -> {new_name}")

print(f"Total files to rename: {len(renames)}")
