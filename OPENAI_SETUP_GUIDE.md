# 🤖 OpenAI API Setup Guide for Smart Meal Planner

This guide explains how to integrate OpenAI API to generate personalized meal plans based on user preferences.

## 📋 Overview

The system now supports AI-powered meal plan generation that considers:
- ✅ Dietary preference (Vegetarian/Non-Vegetarian)
- ✅ Cuisine type (Indian, Chinese, Italian, etc.)
- ✅ Spice level (Mild, Medium, Spicy)
- ✅ Taste preferences (Sweet, Savory, Tangy)
- ✅ Meal types (Main Course, Starter, Dessert)
- ✅ User's inventory items
- ✅ Calorie targets from profile

## 🔑 Getting Your OpenAI API Key

### Step 1: Create OpenAI Account
1. Go to [https://platform.openai.com/signup](https://platform.openai.com/signup)
2. Sign up with your email or Google account
3. Verify your email address

### Step 2: Get API Key
1. Login to [https://platform.openai.com](https://platform.openai.com)
2. Click on your profile icon (top right)
3. Select **"View API keys"**
4. Click **"Create new secret key"**
5. Give it a name (e.g., "Smart Meal Planner")
6. **Copy the key immediately** (you won't see it again!)
7. Store it securely

### Step 3: Add Billing Information
1. Go to **Settings** → **Billing**
2. Add a payment method
3. Set up usage limits (recommended: $10/month for testing)
4. OpenAI charges approximately $0.002 per meal plan generation

## ⚙️ Configuration

### Option 1: Direct Configuration (Quick)
1. Open `api/generate_ai_meal_plan_with_preferences.php`
2. Find line 95: `$api_key = 'YOUR_OPENAI_API_KEY_HERE';`
3. Replace with your actual key: `$api_key = 'sk-proj-xxxxxxxxxxxxx';`
4. Save the file

### Option 2: Environment Variable (Recommended for Production)
1. Create a `.env` file in your project root:
```env
OPENAI_API_KEY=sk-proj-xxxxxxxxxxxxx
```

2. Update the PHP file to read from environment:
```php
$api_key = getenv('OPENAI_API_KEY') ?: 'YOUR_OPENAI_API_KEY_HERE';
```

3. Add `.env` to your `.gitignore` file

## 🧪 Testing

### Without API Key (Fallback Mode)
- The system will generate sample meals automatically
- No AI features, but system still works
- Good for testing the flow

### With API Key
1. Update your profile with health information
2. Click "Generate Meal Plan"
3. Fill in the preferences form:
   - Select Vegetarian or Non-Vegetarian
   - Choose cuisine (e.g., Indian)
   - Select spice level
   - Pick taste preferences
   - Choose meal types
4. Click "Generate Meal Plan"
5. Wait 10-30 seconds for AI to generate meals
6. View your personalized 7-day meal plan!

## 💰 Cost Estimation

### OpenAI Pricing (GPT-4)
- Input: $0.03 per 1K tokens
- Output: $0.06 per 1K tokens
- Average meal plan: ~2000 tokens
- **Cost per meal plan: ~$0.10-0.15**

### Monthly Usage Examples
- 10 users, 1 plan/week: ~$6/month
- 50 users, 1 plan/week: ~$30/month
- 100 users, 2 plans/week: ~$120/month

### Cost Optimization Tips
1. Use GPT-3.5-turbo instead of GPT-4 (10x cheaper)
2. Cache common meal plans
3. Limit regenerations per user
4. Use fallback mode for testing

## 🔄 Switching to GPT-3.5-Turbo (Cheaper)

In `api/generate_ai_meal_plan_with_preferences.php`, change line 118:
```php
// From:
'model' => 'gpt-4',

// To:
'model' => 'gpt-3.5-turbo',
```

**Cost reduction: ~90% cheaper!**

## 🛠️ Troubleshooting

### Error: "AI API error"
- Check if API key is correct
- Verify billing is set up
- Check OpenAI status: [https://status.openai.com](https://status.openai.com)
- System will fallback to sample meals

### Error: "Rate limit exceeded"
- You've hit OpenAI's rate limit
- Wait a few minutes
- Upgrade your OpenAI plan
- Implement request queuing

### Error: "Insufficient quota"
- Add more credits to your OpenAI account
- Check usage at [https://platform.openai.com/usage](https://platform.openai.com/usage)

### Meals not matching preferences
- The AI is working but may need prompt tuning
- Edit the prompt in the PHP file (lines 100-150)
- Add more specific instructions
- Provide example outputs

## 📊 Monitoring Usage

### Check OpenAI Dashboard
1. Go to [https://platform.openai.com/usage](https://platform.openai.com/usage)
2. View daily/monthly usage
3. Set up usage alerts
4. Monitor costs

### Log API Calls (Optional)
Add logging to track usage:
```php
// After API call
file_put_contents('logs/openai_usage.log', 
    date('Y-m-d H:i:s') . " - User: $user_id - Tokens: $tokens\n", 
    FILE_APPEND
);
```

## 🔒 Security Best Practices

1. **Never commit API keys to Git**
   - Use environment variables
   - Add `.env` to `.gitignore`

2. **Restrict API key permissions**
   - Only enable necessary endpoints
   - Set usage limits

3. **Implement rate limiting**
   - Limit requests per user
   - Add cooldown periods

4. **Validate user input**
   - Sanitize preferences
   - Prevent prompt injection

## 🚀 Advanced Features

### Custom Prompts
Edit the prompt in `generate_ai_meal_plan_with_preferences.php` to:
- Add more dietary restrictions
- Include specific ingredients
- Exclude allergens
- Add cooking skill level
- Include meal prep time limits

### Caching
Implement caching to reduce costs:
```php
$cache_key = md5(json_encode($preferences));
if (file_exists("cache/$cache_key.json")) {
    return json_decode(file_get_contents("cache/$cache_key.json"), true);
}
```

### Alternative AI Services
You can also use:
- **Google Gemini** (Free tier available)
- **Anthropic Claude** (Similar pricing)
- **Local LLMs** (Free but requires setup)

## 📝 Sample API Response

```json
{
  "success": true,
  "message": "AI meal plan generated successfully!",
  "meal_plan": {
    "plan_id": 123,
    "meals": [
      {
        "day": 1,
        "meal_type": "breakfast",
        "recipe_name": "Masala Oats",
        "calories": 350,
        "ingredients": [...],
        "instructions": [...]
      }
    ]
  }
}
```

## 🆘 Support

### OpenAI Support
- Documentation: [https://platform.openai.com/docs](https://platform.openai.com/docs)
- Community: [https://community.openai.com](https://community.openai.com)
- Email: support@openai.com

### System Issues
- Check PHP error logs
- Enable error display for debugging
- Test with fallback mode first

## ✅ Checklist

- [ ] Created OpenAI account
- [ ] Got API key
- [ ] Added billing information
- [ ] Configured API key in code
- [ ] Tested with sample preferences
- [ ] Verified meal plan generation
- [ ] Set up usage monitoring
- [ ] Implemented rate limiting (optional)
- [ ] Added error handling
- [ ] Documented for team

---

**Note:** The system works without an API key using fallback sample meals. The AI integration is optional but provides much better personalization!

**Happy Cooking! 🍳**
