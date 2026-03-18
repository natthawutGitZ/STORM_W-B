import ctypes.util
import sys

print("Checking Sodium...")
sodium_path = ctypes.util.find_library('sodium')
print("Sodium Path:", sodium_path)

try:
    import nacl.bindings
    print("PyNaCl Bindings imported successfully!")
except Exception as e:
    print("Failed to import PyNaCl bindings:", e)

try:
    import discord.opus
    print("discord.opus imported successfully!")
    print("Opus loaded?", discord.opus.is_loaded())
except Exception as e:
    print("Failed to load discord.opus:", e)
