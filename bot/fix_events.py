import re

events_file = 'cogs/events.py'
with open(events_file, 'r', encoding='utf-8') as f:
    lines = f.readlines()

# find line 180
# well, instead of line 180, let's look for "@commands.Cog.listener()" that is followed by a broken indent.
# actually, I can just re-extract from server.py (wait, I already deleted it from server.py!)
# So I must fix it in events.py directly.

# Starting at the first newly added listener (which has 4 spaces)
start_idx = -1
for i, line in enumerate(lines):
    if "async def on_member_remove(self, member):" in line:
        start_idx = i - 1 # the decorator
        break

if start_idx == -1:
    print("Not found")
    exit(1)

fixed_lines = lines[:start_idx]
for line in lines[start_idx:]:
    if line.startswith("    @commands.Cog.listener()"):
        fixed_lines.append("    @commands.Cog.listener()\n")
    elif "async def " in line and line.strip().startswith("async def"):
        # this is the def line. we indent it by 4 spaces.
        fixed_lines.append("    " + line.strip() + "\n")
    elif line == "\n":
        fixed_lines.append("\n")
    else:
        # this is the body. The original body had 4 spaces, then got 4. Wait! 
        # The body lines currently have 8 spaces, because they originally had 4, and got 4.
        # But `def` had 0, got 4 from replace, got 4 from loop = 8.
        # So the body originally had 4. In my previous script, I didn't change the body before the loop. The loop added 4.
        # So the body has exactly 8 spaces right now.
        # But wait - if original had 4, and loop added 4, it has 8. And if it had 8, loop added 4, it has 12.
        # So I just need to remove exactly 4 spaces from EVERY line from start_idx onwards, except the decorator and def which I force to 4.
        
        # Actually, let's just strip the 4 spaces from the left if they exist.
        if line.startswith("    "):
            fixed_lines.append(line[4:])
        else:
            fixed_lines.append(line)

with open(events_file, 'w', encoding='utf-8') as f:
    f.writelines(fixed_lines)

print("Fixed")
