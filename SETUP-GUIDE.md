# Shifa shop — setup guide

Your shop is a normal PHP + MySQL website. It works on almost any shared hosting with cPanel (for example Exonhost, XeonBD, Hostinger, Namecheap).

## 1. What to buy

- **Domain**, for example `shifa.com.bd` or `shifashop.com`.
- **Hosting** with **PHP 8.0 or newer**, **MySQL**, and **cPanel**. The smallest plan is enough to start.
- **SSL (https)**. Most hosts give it free (AutoSSL / Let's Encrypt). Turn it on, because customers log in.

## 2. Upload the files

1. In cPanel, open **File Manager** and go to `public_html`.
2. Upload `shifa-shop.zip`, then right-click it and choose **Extract**.
3. Move everything from the `shifa` folder into `public_html`, so `index.php` sits directly inside `public_html`.
   - If you want the shop at `yourdomain.com/shop`, keep it in a `shop` folder instead. That works too.
4. Make sure these folders are **writable** (permission 755): `includes`, `uploads`, `data`.

## 3. Create the database

1. In cPanel, open **MySQL® Databases**.
2. Create a database (for example `shifa`).
3. Create a user with a strong password.
4. **Add the user to the database** with **ALL PRIVILEGES**.
5. Write down the database name, user name and password. cPanel adds a prefix, so the full names look like `abcd_shifa`.

## 4. Run the installer

1. Open `https://yourdomain.com/install.php` in your browser.
2. Choose **MySQL** and enter the database details from step 3.
3. Enter the shop name, the email that should receive new-order alerts, and your admin login.
4. Leave **"Add sample products"** ticked if you want to see how the shop looks. You can delete those products later.
5. Click **Install**. After that, delete `install.php` from File Manager. It is already locked, so this is just extra safety.

Your admin panel is at `https://yourdomain.com/admin/`.

## 5. Set up email (important)

Order emails will not arrive until this is set up.

Go to **Admin → Settings → Email**.

**Option A — Gmail (easiest)**
1. Use a Gmail account for the shop.
2. In your Google Account, turn on **2-Step Verification**.
3. Then open **Security → App passwords** and create one called "Shifa". Copy the 16-letter password.
4. In Settings, click **Fill in Gmail settings**.
5. Enter your Gmail address as the username and the app password as the password.
6. Click **Save & send test email**.

**Option B — Your hosting email**
1. In cPanel, open **Email Accounts** and create an address like `shop@yourdomain.com`.
2. In Settings, click **Fill in hosting (cPanel) settings**.
3. Enter that email and its password, then send a test email.

Then put your own email in **"Send new-order notifications to"**.

## 6. Daily use

| You want to… | Go to |
|---|---|
| Add a category or sub-category (for example Baby Toy › Robot) | **Categories**. Use "+ Sub-category" next to a main category. |
| Add a product (photos, description, price, quantity, weight) | **Products → + Add product** |
| Update stock for a whole category at once | **Stock update**. Pick the category, type new numbers, then Save. |
| See and handle orders | **Orders**. Open one, change its status, and the customer gets an email. |
| Change delivery charges by weight | **Delivery charges** |
| Email your customers about new products | **Marketing email** |
| Download customer names, emails and phones (Excel) | **Customers → Download CSV** |
| Change logo, phone, address | **Settings → Shop details** |

**Turning on Men Items later:**
1. Go to **Categories → Men Items → Edit**.
2. Untick **"Show 'Will be available soon'"**.
3. Add sub-categories and products.

**Order statuses:** Order placed → Confirmed → Packing → On the way → Delivered (or Cancelled).
- Cancelling an order puts its items back into stock.
- Marking an order Delivered marks it as paid.

**Tracking numbers** look like `SHF261017001`. That is SHF + date (YYMMDD) + order number of the day. Customers track their order with this number and their mobile number.

## 7. Backups

- Use cPanel **Backup** (or your host's automatic backups) at least once a week.
- The important parts are the **database** and the **uploads** folder (your product photos).

## 8. Adding bKash / Nagad later

Orders already store a payment method and payment status, so online payment can be added later without changing old orders. You will need a **bKash Merchant account** (or SSLCommerz / ShurjoPay) with API keys.
