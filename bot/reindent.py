events_file = 'cogs/events.py'
with open(events_file, 'r', encoding='utf-8') as f:
    lines = f.readlines()

start_idx = -1
end_idx = -1
for i, line in enumerate(lines):
    if "async def on_member_remove(self, member):" in line:
        start_idx = i + 1 # start modifying from the line AFTER the def
    if "async def setup(bot: commands.Bot):" in line:
        end_idx = i - 1 # Stop BEFORE the setup function
        break

if start_idx == -1 or end_idx == -1:
    print("Markers not found!")
    exit(1)

fixed_lines = lines[:start_idx]

for i in range(start_idx, end_idx):
    line = lines[i]
    if line.strip() == "":
        fixed_lines.append(line)
    elif line.startswith("    @commands.Cog.listener()"):
        fixed_lines.append(line)
    elif line.startswith("    async def ") or line.startswith("    def "):
        fixed_lines.append(line)
    else:
        # Add 4 spaces to the body lines
        fixed_lines.append("    " + line)

fixed_lines.extend(lines[end_idx:])

with open(events_file, 'w', encoding='utf-8') as f:
    f.writelines(fixed_lines)

print("Indentation fixed.")
