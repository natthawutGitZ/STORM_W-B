import re

server_file = 'api/server.py'
events_file = 'cogs/events.py'

with open(server_file, 'r', encoding='utf-8') as f:
    server_code = f.read()

start_marker = "async def on_member_remove(self, member):"
end_marker = "# --- FEED API HANDLERS ---"

start_idx = server_code.find(start_marker)
end_idx = server_code.find(end_marker, start_idx)

if start_idx == -1 or end_idx == -1:
    print("Markers not found!")
    exit(1)

extracted_code = server_code[start_idx:end_idx].strip()
print(f"Extracted {len(extracted_code)} chars")

new_server_code = server_code[:start_idx] + "\n" + server_code[end_idx:]
with open(server_file, 'w', encoding='utf-8') as f:
    f.write(new_server_code)
print("Removed from server.py")

# Modify extracted_code
extracted_code = extracted_code.replace("async def on_member", "@commands.Cog.listener()\n    async def on_member")
extracted_code = extracted_code.replace("async def on_message", "@commands.Cog.listener()\n    async def on_message")
extracted_code = extracted_code.replace("self.get_channel", "self.bot.get_channel")
extracted_code = extracted_code.replace("self.get_feed_monitored_channels", "self.get_feed_monitored_channels")
extracted_code = extracted_code.replace("self.store_feed_message", "self.store_feed_message")
extracted_code = extracted_code.replace("def get_feed_monitored_channels(self):", "def get_feed_monitored_channels(self):")

lines = extracted_code.split('\n')
new_lines = []
for line in lines:
    if line.startswith("@commands.Cog.listener()"):
        new_lines.append("    " + line)
    elif line == "":
        new_lines.append("")
    else:
        new_lines.append("    " + line)
extracted_code = '\n'.join(new_lines)

with open(events_file, 'r', encoding='utf-8') as f:
    events_code = f.read()

imports_to_add = """import os
import io
import redis

REDIS_HOST = os.getenv('REDIS_HOST', 'redis')
r = redis.Redis(host=REDIS_HOST, port=6379, db=0, decode_responses=True)
"""
if "import redis" not in events_code:
    events_code = events_code.replace("import json\n", "import json\n" + imports_to_add)

setup_marker = "async def setup(bot: commands.Bot):"
setup_idx = events_code.find(setup_marker)

if setup_idx == -1:
    print("Setup marker not found in events.py!")
    exit(1)

new_events_code = events_code[:setup_idx] + extracted_code + "\n\n" + events_code[setup_idx:]

with open(events_file, 'w', encoding='utf-8') as f:
    f.write(new_events_code)

print("Injected into events.py")
