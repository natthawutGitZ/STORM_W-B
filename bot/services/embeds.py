import discord
from .database import DatabaseService

class EmbedService:
    @staticmethod
    def create_embed(data):
        """
        Creates a discord.Embed object from a JSON dictionary (MEE6-style schema).
        Schema Reference: structure.txt
        """
        # Save to Database (Optional: Add specific logic if needed)
        try:
            # Note: We might need a save_embed method in DatabaseService
            pass
        except Exception as e:
            print(f"Embed Save Error: {e}")

        # 1. Resolve Style & Colors
        style = data.get('style', {})
        color_raw = style.get('color', '#c5a059')
        if isinstance(color_raw, str):
            color = int(color_raw.replace('#', ''), 16)
        else:
            color = color_raw

        embed = discord.Embed(
            color=color,
            timestamp=discord.utils.utcnow() if style.get('timestamp') else None
        )

        # 2. Content (Title, Description)
        content = data.get('content', {})
        if 'title' in content and content['title']:
             embed.title = str(content['title'])
        
        if content.get('description'):
            embed.description = str(content['description'])

        # 3. Branding (Author, Footer)
        branding = data.get('branding', {})
        if branding.get('enabled'):
            if branding.get('author_name'):
                embed.set_author(
                    name=branding.get('author_name'), 
                    icon_url=branding.get('author_icon')
                )
            if branding.get('footer_text'):
                embed.set_footer(
                    text=branding.get('footer_text'),
                    icon_url=branding.get('footer_icon')
                )

        # 4. Images & Thumbnails
        if style.get('thumbnail'):
            embed.set_thumbnail(url=style.get('thumbnail'))
        
        if style.get('image'):
            embed.set_image(url=style.get('image'))

        # 5. Fields
        fields = content.get('fields', [])
        for f in fields:
            embed.add_field(
                name=f.get('name', '-'),
                value=f.get('value', '-'),
                inline=f.get('inline', False)
            )

        # Check if embed is empty (has no visible content)
        # An embed needs at least a title, description, field, image, thumbnail, author, or footer.
        if not any([
            embed.title,
            embed.description,
            embed.fields,
            embed.image,
            embed.thumbnail,
            embed.footer,
            embed.author
        ]):
            return None

        return embed
