import os

server_file = 'api/server.py'
events_file = 'cogs/events.py'

with open(server_file, 'r', encoding='utf-8') as f:
    server_code = f.read()

start_marker = "async def play_welcome_sound_in_channel(self, voice_client):"
end_marker = "# --- FEED API HANDLERS ---"

start_idx = server_code.find(start_marker)
end_idx = server_code.find(end_marker, start_idx)

if start_idx == -1 or end_idx == -1:
    print("Markers not found in server.py!")
    exit(1)

extracted = server_code[start_idx:end_idx].strip()
new_server = server_code[:start_idx] + "\n" + server_code[end_idx:]

with open(server_file, 'w', encoding='utf-8') as f:
    f.write(new_server)

print("Removed from server.py")

# Process extracted content
lines = extracted.split('\n')
new_lines = []
for line in lines:
    if line.startswith("async def on_voice_state_update"):
        new_lines.append("    @commands.Cog.listener()")
        new_lines.append("    " + line)
    elif line.startswith("async def play_welcome_sound_in_channel"):
        new_lines.append("    " + line)
    else:
        new_lines.append("    " + line)

extracted = '\n'.join(new_lines)
extracted = extracted.replace("self.get_channel", "self.bot.get_channel")

with open(events_file, 'r', encoding='utf-8') as f:
    events_code = f.read()

# Add imports if missing
if "from ui.components import" in events_code:
    if "WelcomeView" not in events_code:
        events_code = events_code.replace("from ui.components import ", "from ui.components import WelcomeView, ")

if "BANGKOK_TZ" not in events_code:
    imports_to_add = "\nimport asyncio\nfrom datetime import timezone, timedelta\nBANGKOK_TZ = timezone(timedelta(hours=7))\n"
    events_code = events_code.replace("import discord", "import discord" + imports_to_add)

setup_marker = "async def setup(bot: commands.Bot):"
setup_idx = events_code.find(setup_marker)

if setup_idx == -1:
    print("setup marker not found in events.py!")
    exit(1)

new_events = events_code[:setup_idx] + extracted + "\n\n" + events_code[setup_idx:]

with open(events_file, 'w', encoding='utf-8') as f:
    f.write(new_events)

print("Injected into events.py")
