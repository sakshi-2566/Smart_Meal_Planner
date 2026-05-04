# OpenAI API Key Configuration Guide

🎉 **GOOD NEWS!** The system now uses centralized environment configuration. You only need to update the API key in **ONE PLACE**!

## Quick Setup (Recommended)

### Option 1: Use the Setup Interface
1. Open your browser and go to: `http://localhost/setup_env.php`
2. Enter your new OpenAI API key in the form
3. Click "Update Configuration"
4. Test the connection using the "Test OpenAI API" button

### Option 2: Edit .env File Directly
1. Open the `.env` file in the root directory
2. Update the line: `OPENAI_API_KEY=your_new_api_key_here`
3. Save the file

That's it! The change will be applied everywhere automatically.

## How It Works

The system now uses a centralized configuration system:

- **Environment File:** `.env` (stores your API key)
- **Config Helper:** `config/env.php` (loads the key everywhere)
- **Auto-Loading:** All files automatically use the centralized key

## File Structure

```
Smart_Meal_Planner/
├── .env                          # 🔑 Your API key goes here
├── config/env.php               # 📋 Configuration loader
├── setup_env.php               # 🛠️ Setup interface
└── api/                        # ✅ All files use centralized config
    ├── generate_ai_meal_plan_with_preferences.php
    ├── auto_generate_meal_plan.php
    └── ...
```

## Environment File (.env)

Your `.env` file should look like this:

```env
# OpenAI Configuration
OPENAI_API_KEY=sk-proj-your-actual-api-key-here

# Database Configuration
DB_HOST=localhost
DB_USERNAME=root
DB_PASSWORD=
DB_NAME=smart_meal_planner

# Application Settings
APP_NAME=Smart Meal Planner
APP_ENV=development
APP_DEBUG=true
```

## Benefits of New System

✅ **Single Point of Change:** Update API key in one place  
✅ **Automatic Loading:** All files use the same configuration  
✅ **Security:** .env file can be excluded from version control  
✅ **Easy Management:** Web interface for configuration  
✅ **Backward Compatible:** Still works if .env is missing  

## Testing Your Configuration

### Method 1: Setup Interface
- Go to `setup_env.php` and click "Test OpenAI API"

### Method 2: Direct Test
- Go to `test_openai.php` to test the API connection

### Method 3: Application Test
- Generate a meal plan from the dashboard
- Try AI recipe generation from inventory

## Troubleshooting

### API Key Not Working?
1. Check the `.env` file exists and has the correct key
2. Ensure no extra spaces around the key
3. Verify the key is valid in OpenAI dashboard
4. Check file permissions (web server must read .env)

### Files Not Finding Configuration?
- The system automatically creates `.env` if missing
- All files now include `config/env.php` automatically
- Fallback system ensures compatibility

### Common Error Messages:
- `"OpenAI API key not found"` → Check .env file
- `"Invalid API key"` → Verify key in OpenAI dashboard  
- `"Permission denied"` → Check file permissions

## Security Best Practices

🔒 **Important Security Notes:**

1. **Never commit .env to git:**
   ```bash
   echo ".env" >> .gitignore
   ```

2. **Restrict file permissions:**
   ```bash
   chmod 600 .env
   ```

3. **Use environment variables in production:**
   - Set `OPENAI_API_KEY` as server environment variable
   - The system will automatically use it

4. **Regular key rotation:**
   - Generate new keys periodically in OpenAI dashboard
   - Update .env file with new key

## Migration from Old System

If you're upgrading from the old system where keys were hardcoded:

1. **Automatic Migration:** The new system includes fallbacks
2. **No Breaking Changes:** Everything continues to work
3. **Gradual Transition:** Old hardcoded keys still work as backup
4. **Clean Setup:** Use `setup_env.php` for fresh configuration

## Advanced Configuration

### Custom Environment Path
```php
// Load from custom location
EnvLoader::load('/path/to/custom/.env');
```

### Check Configuration Status
```php
// Check if OpenAI is properly configured
if (EnvLoader::isOpenAIConfigured()) {
    echo "OpenAI is ready!";
}
```

### Get Configuration Values
```php
// Get any environment variable
$apiKey = env('OPENAI_API_KEY');
$dbHost = env('DB_HOST', 'localhost'); // with default
```

## Quick Commands

```bash
# Check if .env exists
ls -la .env

# View current configuration (be careful with API key!)
cat .env

# Test file permissions
ls -la .env

# Search for old hardcoded keys (should find none now)
grep -r "sk-proj-" . --exclude-dir=.git
```

## Support

- **Setup Issues:** Use `setup_env.php` interface
- **API Problems:** Check `test_openai.php`
- **Configuration Help:** Review this guide
- **File Permissions:** Contact your hosting provider

---

**🎯 Remember:** With the new system, you only need to update your API key in the `.env` file, and it will work everywhere automatically!