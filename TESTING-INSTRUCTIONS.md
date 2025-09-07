# Testing Instructions for Flag Awards & Currency Pricing

## 🧪 **Quick Test Checklist**

### **Test 1: Admin Fields Visibility**
1. Go to **WordPress Admin → Products → Add New** (or edit existing)
2. Scroll to **Product Data** box
3. Click **General** tab
4. Look for these sections:

#### ✅ **Expected Sections:**
- **Flag Awards** (checkbox + configuration)
- **Currency Pricing** (table with Add Currency Price button)  
- **Item-Based Special Pricing** (table with Add Item Price button)
- **Item & Currency Awards** (when purchased sections)

#### 🚨 **If Missing:**
- Check WordPress error log for class loading issues
- Verify WooCommerce is active and updated
- Check if Membershiping core plugin is active

### **Test 2: Flag Awards Configuration**
1. ☑️ Check **"Award flags when this product is purchased"**
2. Click **"Add Flag Award"**
3. Configure:
   - **Flag Name**: `test_flag`
   - **Quantity**: `1`
   - **Type**: `Add`
4. **Save Product**

### **Test 3: Currency Pricing Configuration**
1. ☑️ Check **"Allow Currency Payment"**
2. Click **"Add Currency Price"**
3. Configure:
   - **Currency**: Select available currency
   - **Price**: Enter price (e.g., `100`)
4. **Save Product**

### **Test 4: Frontend Currency Display**
1. Go to **product page** (frontend)
2. Look for:
   - ✅ Currency price under regular price: "Or 100 💎Diamonds"
   - ✅ Payment Options section
   - ✅ Balance information

3. Add to **cart** and check:
   - ✅ Currency options in cart item details
   
4. Go to **checkout** and verify:
   - ✅ Currency payment checkboxes
   - ✅ Balance validation

### **Test 5: Purchase Flow**
1. **Complete a test purchase** with flag awards enabled
2. Check **user account** (admin):
   - Go to **Membershiping → User Flags**
   - Click **User Assignments** tab
   - Verify flag was awarded

---

## 🔧 **Troubleshooting**

### **Admin Fields Not Showing:**
```php
// Add this to wp-config.php temporarily to see errors:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check error log at: /wp-content/debug.log
```

### **Currency Prices Not Displaying:**
- Verify product has "Allow Currency Payment" checked
- Check if currencies exist in **Membershiping → Currencies**
- Look for JavaScript console errors

### **Flag Awards Not Working:**
- Ensure flags exist in **Membershiping → User Flags**
- Flag names must match exactly (case-sensitive)
- Check order status is "Processing" or "Completed"

---

## 📋 **Success Criteria**

### ✅ **Admin Interface:**
- [ ] Flag Awards section visible
- [ ] Currency Pricing section visible  
- [ ] Item-Based Pricing section visible
- [ ] All sections save data correctly

### ✅ **Frontend Display:**
- [ ] Currency prices show on product page
- [ ] Currency options show in cart
- [ ] Currency checkout options work
- [ ] Balance validation works

### ✅ **Purchase Flow:**
- [ ] Flags awarded on purchase
- [ ] Currency payments deduct balance
- [ ] Order processing works correctly

---

**If all tests pass, the integration is working correctly! 🎉**
