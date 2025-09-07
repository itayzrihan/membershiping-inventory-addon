# Flag Awards & Currency Pricing Guide

## 🏆 **DEFINING FLAG AWARDS FOR PRODUCT PURCHASES**

### **Step 1: Edit Product in WordPress Admin**
1. Go to **Products → Edit Product**
2. Scroll to **Product Data** box
3. Click **General** tab
4. Find **"Flag Awards"** section

### **Step 2: Enable Flag Awards**
- ☑️ Check **"Award flags when this product is purchased"**
- The configuration section will appear

### **Step 3: Configure Flag Awards**
For each flag you want to award, set:

| Field | Description | Example |
|-------|-------------|---------|
| **Flag Name** | Internal flag identifier | `gold_coins`, `xp_points`, `vip_level` |
| **Quantity** | Amount to award per product | `100`, `50`, `1` |
| **Type** | How to apply the award | `Add`, `Set`, `Multiply` |

### **Award Types Explained:**
- **Add**: Adds quantity to existing flag value
- **Set**: Sets flag to specific value (overwrites)
- **Multiply**: Multiplies existing flag value by quantity

### **Example Configuration:**
```
🪙 Flag: gold_coins    | Quantity: 100 | Type: Add      → User gets +100 gold coins
⭐ Flag: xp_points     | Quantity: 50  | Type: Add      → User gets +50 XP
👑 Flag: vip_level     | Quantity: 2   | Type: Set      → User becomes VIP level 2
🔥 Flag: streak_bonus  | Quantity: 2   | Type: Multiply → Doubles current streak
```

### **When Flags Are Awarded:**
- ✅ **Order Status: Processing** (payment confirmed)
- ✅ **Order Status: Completed** (order fulfilled)
- 🔄 **Auto-retry** if flag system is temporarily unavailable
- 📧 **Guest Support** - flags stored for claiming when user registers

---

## 💰 **CURRENCY PRICING DISPLAY SETUP**

### **Step 1: Enable Currency Payment for Products**
1. **Edit Product** → **Product Data** → **General**
2. ☑️ Check **"Allow Currency Payment"**
3. Click **"Add Currency Price"** to configure pricing

### **Step 2: Configure Currency Prices**
| Field | Description | Example |
|-------|-------------|---------|
| **Currency** | Select from available currencies | `Diamonds 💎`, `Gold Coins 🪙` |
| **Price** | Cost in that currency | `150`, `500` |

### **Current Display Locations:**

#### 📄 **Product Page**
- ✅ **Price Display**: Shows "Or 150 💎Diamonds" under regular price
- ✅ **Payment Options**: Section showing available currency payments
- ✅ **Balance Check**: Shows user's current balance
- ✅ **Affordability**: Disables option if insufficient funds

#### 🛒 **Cart Page** 
- ✅ **Item Details**: Currency prices shown under product names
- ✅ **Payment Selection**: Choose currency payment per item
- ✅ **Balance Validation**: Real-time balance checking

#### 💳 **Checkout Page**
- ✅ **Currency Options**: Checkboxes for currency payments
- ✅ **Payment Summary**: Shows total reduction from currency payments  
- ✅ **Balance Warnings**: Clear insufficient funds messages
- ✅ **Order Total**: Automatically adjusts when currency is used

### **Display Examples:**

**Product Page:**
```
Regular Price: $29.99
Or 150 💎Diamonds
Or 500 🪙Gold Coins

💰 Payment Options:
☑️ Pay with 150 💎Diamonds (Your balance: 250 💎Diamonds)
☐ Pay with 500 🪙Gold Coins (Your balance: 200 🪙Gold Coins - Insufficient funds)
```

**Checkout Page:**
```
🎮 Gaming Package (×1)
☑️ Pay 150 💎Diamonds (Total: 150) ✅ Your balance: 250 💎Diamonds
☐ Pay 500 🪙Gold Coins (Total: 500) ❌ Insufficient funds

💰 Currency Payment Summary:
• Gaming Package: 150 💎Diamonds
Order total reduction: $29.99
```

---

## 🔧 **TECHNICAL IMPLEMENTATION**

### **Database Storage:**
- **Flag Awards**: `_membershiping_flag_awards` (product meta)
- **Currency Prices**: `_membershiping_currency_prices` (product meta)
- **User Balances**: `wp_membershiping_inventory_user_currencies` table
- **Flag Values**: Integrated with Membershiping Core flag system

### **Key Files:**
- **Flag Awards**: `/includes/class-flag-awards.php`
- **Currency System**: `/includes/class-enhanced-woocommerce-integration.php`
- **Admin Interface**: WooCommerce product meta boxes

### **Automatic Processing:**
- **Order Hook**: `woocommerce_order_status_completed`
- **Payment Validation**: Real-time AJAX balance checking
- **Guest Support**: Temporary storage with email claiming system
- **Security**: All transactions logged and validated

---

## 🎯 **QUICK CHECKLIST**

### **For Flag Awards:**
- [ ] Edit product in WP Admin
- [ ] Enable "Award flags when this product is purchased"  
- [ ] Add flag name, quantity, and type
- [ ] Test with a purchase
- [ ] Check user's flag values in admin

### **For Currency Pricing:**
- [ ] Edit product in WP Admin
- [ ] Enable "Allow Currency Payment"
- [ ] Add currency and price combinations
- [ ] Test on product page (should show "Or X Currency")
- [ ] Test cart page (should show payment options)
- [ ] Test checkout (should show currency selection)
- [ ] Verify balance deduction after purchase

### **Troubleshooting:**
- **Currency not showing?** → Check if "Allow Currency Payment" is enabled
- **Flags not awarded?** → Check order status and review error logs
- **Balance issues?** → Verify user has sufficient currency balance
- **Guest purchases?** → Flags stored temporarily, awarded on registration

---

## 🚀 **READY TO USE!**

Your system is now configured to:
- ✅ Award flags automatically when products are purchased
- ✅ Display currency pricing on all WooCommerce pages
- ✅ Handle both registered users and guests
- ✅ Validate balances and prevent overspending
- ✅ Provide comprehensive admin controls

Both flag awards and currency pricing are **fully implemented and active** in your addon!
