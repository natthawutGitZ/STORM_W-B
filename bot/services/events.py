import discord
from discord.ui import View, Button
from datetime import datetime
import aiohttp
import os
from .database import DatabaseService

class EventService:
    @staticmethod
    def create_event_embed(data):
        """
        Creates an Apollo-style Event Embed.
        Schema Reference: structure.txt (EVENT DATA SCHEMA)
        """
        # Save to Database
        try:
            DatabaseService.save_event(data)
        except Exception as e:
            print(f"DB Save Error: {e}")
        
        # Extract Data
        title = data.get('title', 'Untitled Event')
        story = data.get('story', '')
        # Timestamps
        start_time_str = data.get('start_time') # ISO 8601 expected
        
        # Parse Time
        try:
            dt = datetime.fromisoformat(start_time_str.replace('Z', '+00:00'))
        except:
            dt = datetime.now() # Fallback

        ts = int(dt.timestamp())
        
        # Embed Color based on Category
        category = data.get('category', 'other').lower()
        color_map = {
            'milsim': 0xe74c3c,    # Red
            'training': 0xf1c40f,  # Yellow
            'meeting': 0x3498db,   # Blue
            'fun': 0x9b59b6,       # Purple
            'other': 0x95a5a6      # Gray
        }
        color = color_map.get(category, 0x95a5a6)

        embed = discord.Embed(
            title=f"📅 {title}",
            description=story,
            color=color
        )
        

        
        # Calculate duration (default 3 hours if not specified)
        duration_hours = data.get('duration', 3)
        end_ts = ts + (int(duration_hours) * 3600)
        
        # Google Calendar Link
        gcal_start = dt.strftime('%Y%m%dT%H%M%S')
        gcal_title = title.replace(' ', '+')
        gcal_url = f"https://calendar.google.com/calendar/render?action=TEMPLATE&text={gcal_title}&dates={gcal_start}/{gcal_start}"
        
        # Time field with Discord timestamps (auto-adapts to user timezone)
        embed.add_field(
            name="⏰ Time", 
            value=f"<t:{ts}:F> - <t:{end_ts}:t> [[Add to Google]]({gcal_url})\n🕐 <t:{ts}:R>", 
            inline=False
        )
        
        # Requirements
        reqs = data.get('requirements', {})
        if reqs.get('mods'):
             # Handle list or string
             mods = reqs['mods']
             if isinstance(mods, list):
                 mods = "\n".join(mods)
             embed.add_field(name="🛠️ Mods", value=mods, inline=True)
        
        # Add Separator to force new row for RSVP fields
        embed.add_field(name='\u200b', value='\u200b', inline=False)
        
        # Default RSVP fields (show immediately with 0 count)
        embed.add_field(name="✅ Accepted (0)", value="-", inline=True)
        embed.add_field(name="❌ Declined (0)", value="-", inline=True)
        embed.add_field(name="❔ Tentative (0)", value="-", inline=True)
        
        # Event Image
        if data.get('image'):
            embed.set_image(url=data.get('image'))
        
        # Footer
        embed.set_footer(text=f"Event ID: {data.get('event_id')} • Status: {data.get('status', 'scheduled').upper()}")
        
        return embed

    @staticmethod
    def update_embed_with_rsvps(embed, rsvps):
        # Group by choice
        groups = {'going': [], 'decline': [], 'maybe': []}
        for r in rsvps:
            c = r.get('choice')
            u = r.get('username', 'Unknown')
            if c in groups:
                 groups[c].append(u)
                 
        # Remove old RSVP fields
        to_keep = []
        for f in embed.fields:
             # Check if field is one of our RSVP fields
             if not (f.name.startswith("✅ Accepted") or f.name.startswith("❌ Declined") or f.name.startswith("❔ Tentative") or f.name == '\u200b'):
                 to_keep.append(f)
                 
        embed.clear_fields()
        for f in to_keep:
            embed.add_field(name=f.name, value=f.value, inline=f.inline)

        # Add Separator to force new row
        embed.add_field(name='\u200b', value='\u200b', inline=False)
            
        # Add new RSVP fields
        # Accepted
        acc = groups['going']
        acc_val = "\n".join(acc) if acc else "-"
        embed.add_field(name=f"✅ Accepted ({len(acc)})", value=acc_val[:1024], inline=True)

        # Declined
        dec = groups['decline']
        dec_val = "\n".join(dec) if dec else "-"
        embed.add_field(name=f"❌ Declined ({len(dec)})", value=dec_val[:1024], inline=True)

        # Tentative
        tent = groups['maybe']
        tent_val = "\n".join(tent) if tent else "-"
        embed.add_field(name=f"❔ Tentative ({len(tent)})", value=tent_val[:1024], inline=True)
        
        return embed

class RSVPView(View):
    def __init__(self, event_id):
        super().__init__(timeout=None) # Persistent view
        self.event_id = event_id

    async def handle_rsvp(self, interaction: discord.Interaction, choice: str):
        # Defer interaction to allow DB ops and message edit
        await interaction.response.defer()
        
        user_id = interaction.user.id
        username = interaction.user.display_name 
        
        # 1. Save to Bot Database (Postgres)
        try:
            DatabaseService.save_rsvp(self.event_id, user_id, choice, username)
        except Exception as e:
            print(f"RSVP DB Error: {e}", flush=True)
            await interaction.followup.send(f"Internal Database Error: {str(e)[:100]}", ephemeral=True)
            return

        # 2. Update Embed with new List (preserve image!)
        try:
            rsvps = DatabaseService.get_rsvps(self.event_id)
            original_embed = interaction.message.embeds[0]
            
            # Create a brand new embed to avoid any mutation issues
            new_embed = discord.Embed(
                title=original_embed.title,
                description=original_embed.description,
                color=original_embed.color
            )
            
            # Copy non-RSVP fields from original embed
            for f in original_embed.fields:
                if not (f.name.startswith("✅ Accepted") or f.name.startswith("❌ Declined") or f.name.startswith("❔ Tentative") or f.name == '\u200b'):
                    new_embed.add_field(name=f.name, value=f.value, inline=f.inline)
            
            # Add Separator
            new_embed.add_field(name='\u200b', value='\u200b', inline=False)
            
            # Add updated RSVP fields
            groups = {'going': [], 'decline': [], 'maybe': []}
            for r in rsvps:
                c = r.get('choice')
                u = r.get('username', 'Unknown')
                if c in groups:
                    groups[c].append(u)
                    
            # Accepted
            acc = groups['going']
            acc_val = "\n".join(acc) if acc else "-"
            new_embed.add_field(name=f"✅ Accepted ({len(acc)})", value=acc_val[:1024], inline=True)
            
            # Declined
            dec = groups['decline']
            dec_val = "\n".join(dec) if dec else "-"
            new_embed.add_field(name=f"❌ Declined ({len(dec)})", value=dec_val[:1024], inline=True)
            
            # Tentative
            tent = groups['maybe']
            tent_val = "\n".join(tent) if tent else "-"
            new_embed.add_field(name=f"❔ Tentative ({len(tent)})", value=tent_val[:1024], inline=True)
            
            # Copy footer
            if original_embed.footer:
                new_embed.set_footer(text=original_embed.footer.text, icon_url=original_embed.footer.icon_url if hasattr(original_embed.footer, 'icon_url') else None)
            
            # CRITICAL: Copy the image from original embed to preserve it
            # When using attachment://, the file is already attached to the message
            # We just need to reference it again in the new embed
            if original_embed.image and original_embed.image.url:
                new_embed.set_image(url=original_embed.image.url)
            
            # Edit message with new embed, attachments are preserved automatically
            await interaction.message.edit(embed=new_embed)
        except Exception as e:
            print(f"Embed Update Error: {e}", flush=True)

        # 3. Fire Callback to PHP
        php_callback_url = os.getenv('PHP_API_URL', 'http://web/api/rsvp.php')
        payload = {
            "event_id": self.event_id,
            "user_id": str(user_id),
            "username": str(interaction.user),
            "choice": choice
        }
        
        try:
            async with aiohttp.ClientSession() as session:
                async with session.post(php_callback_url, json=payload) as resp:
                    # Just fire and forget or minimal log
                    pass
        except Exception as e:
            print(f"PHP Callback Error: {e}")

    @discord.ui.button(label="✅", style=discord.ButtonStyle.secondary, custom_id="rsvp_going")
    async def going_button(self, interaction: discord.Interaction, button: Button):
        await self.handle_rsvp(interaction, "going")

    @discord.ui.button(label="❌", style=discord.ButtonStyle.secondary, custom_id="rsvp_decline")
    async def decline_button(self, interaction: discord.Interaction, button: Button):
        await self.handle_rsvp(interaction, "decline")

    @discord.ui.button(label="❔", style=discord.ButtonStyle.secondary, custom_id="rsvp_maybe")
    async def maybe_button(self, interaction: discord.Interaction, button: Button):
        await self.handle_rsvp(interaction, "maybe")

    @discord.ui.button(label="Edit", style=discord.ButtonStyle.green, custom_id="event_edit")
    async def edit_button(self, interaction: discord.Interaction, button: Button):
        # Only allow admins/event creators to edit
        # For now, respond with edit link
        await interaction.response.defer(ephemeral=True)
        
        # Build edit URL (PHP admin panel)
        base_url = os.getenv('APP_URL', 'http://localhost')
        edit_url = f"{base_url}/admin/bot_controls.php?edit_event={self.event_id}"
        
        await interaction.followup.send(
            f"📝 [Click here to edit this event]({edit_url})",
            ephemeral=True
        )

    @discord.ui.button(label="Delete", style=discord.ButtonStyle.red, custom_id="event_delete")
    async def delete_button(self, interaction: discord.Interaction, button: Button):
        # Only allow admins/event creators to delete
        await interaction.response.defer(ephemeral=True)
        
        # Confirm deletion
        try:
            # Delete from database
            DatabaseService.delete_event(self.event_id)
            
            # Delete the message
            await interaction.message.delete()
            
            await interaction.followup.send(
                f"🗑️ Event `{self.event_id}` has been deleted.",
                ephemeral=True
            )
        except Exception as e:
            print(f"Delete Event Error: {e}", flush=True)
            await interaction.followup.send(
                f"❌ Failed to delete event: {str(e)[:100]}",
                ephemeral=True
            )
