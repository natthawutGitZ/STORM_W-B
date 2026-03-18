# Railway Deployment for S.T.O.R.M Discord Bot

## Files to Upload
Upload the entire `bot/` folder to Railway:
- bot.py
- requirements.txt
- Procfile
- railway.json
- services/ (entire folder)

## Environment Variables (set in Railway Dashboard)
| Variable | Value |
|----------|-------|
| DISCORD_BOT_TOKEN | Your Discord Bot Token |
| DATABASE_URL | Railway PostgreSQL URL (auto-provided if you add PostgreSQL) |
| REDIS_URL | Railway Redis URL (auto-provided if you add Redis) |

## Steps
1. Go to https://railway.app and login with GitHub
2. Click "New Project"
3. Select "Deploy from GitHub Repo" or "Empty Project"
4. Upload/connect your bot folder
5. Add PostgreSQL and Redis from "New" button
6. Set environment variables
7. Deploy!

## After Deployment
Update `src/includes/bot_api.php` on InfinityFree:
```php
private $botApiUrl = 'https://YOUR-RAILWAY-URL.up.railway.app';
```
