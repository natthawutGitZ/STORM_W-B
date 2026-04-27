
import os

file_path = r'c:\xampp\htdocs\TEST\assets\css\style.css'

with open(file_path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

# Lines to remove: 976 to 1223 (1-based)
# Indices: 975 to 1222 (0-based)
# Slice: [975:1223] (since end is exclusive)

start_idx = 975
end_idx = 1223

# Verify content before deleting (safety check)
if "/* Responsive */" not in lines[start_idx]:
    print(f"Error: Line {start_idx+1} does not match expected content.")
    print(f"Content: {lines[start_idx]}")
    exit(1)

# Remove lines
del lines[start_idx:end_idx]

# Insert closing brace
lines.insert(start_idx, "    }\n")

with open(file_path, 'w', encoding='utf-8') as f:
    f.writelines(lines)

print("Successfully fixed style.css")
