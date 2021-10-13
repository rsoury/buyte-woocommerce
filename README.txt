=== Buyte ===
Contributors: webdoodle
Tags: apple pay, google pay, fast checkout, digital wallet, mobile first, mobile checkout
Stable tag: 0.2.5
Tested up to: 5.7.2
License: GPLv2 or later License http://www.gnu.org/licenses/gpl-2.0.html

Buyte WooCommerce Plugin enables checkout using Apple Pay and Google Pay in a simple, codeless install. Accelerate your customer experience with a bite-sized checkout.

== Description ==

**What is Buyte?**
Buyte is a open-source digital wallet payment orchestration platform that simplifies the process of getting digital wallets like Apple Pay and Google Pay set up on your eCommerce websites. 
You can find more information about Buyte here: [https://github.com/rsoury/buyte](https://github.com/rsoury/buyte)

**How does it work?**
Buyte works by loading a small widget on the eCommerce website that loads widely used digital wallets, hassle-free.
When digital wallets are interacted with, Buyte provides an interface to carry out the entire checkout process fast and securely.
To facilitate the checkout, Buyte uses the store's existing shipping settings and acquires order and customer data only when a customer authorises their payment with one of Buyte's provided digital wallets.

**Why use Buyte?**
Buyte abstracts the compliance, security and development requirements to load these digital wallets out of the box.
Offer your customers Apple Pay and Google Pay in a single install. By integrating Buyte into your e-commerce website, your visitors can securely checkout with their mobile wallet. Don't force credit card forms and user login forms on your new visitors.

== Setup ==
1. Deploy [Buyte](https://github.com/rsoury/buyte) to your cloud
2. Configure your payment gateway in the [Buyte Dashboard](https://github.com/rsoury/buyte-dashboard)
3. Use your Buyte API keys and Checkout Widget ID in the plugin configuration

== Contributions ==

Feel free to submit any pull requests here: [https://github.com/rsoury/buyte-woocommerce](https://github.com/rsoury/buyte-woocommerce)

== Installation ==

= Automatic Installation =
*   Login to your WordPress Admin area
*   Go to "Plugins > Add New" from the left-hand menu
*   In the search box type "Buyte"
*   From the search result you will see "Buyte" click on "Install Now" to install the plugin
*   A popup window will ask you to confirm your wish to install the Plugin.

= Note: =
If this is the first time you've installed a WordPress Plugin, you may need to enter the FTP login credential information. If you've installed a Plugin before, it will still have the login information. This information is available through your web server host.

* Click "Proceed" to continue the installation. The resulting installation screen will list the installation as successful or note any problems during the install.
* If successful, click "Activate Plugin" to activate it, or "Return to Plugin Installer" for further actions.

= Manual Installation =
1.  Download the plugin zip file
2.  Login to your WordPress Admin. Click on "Plugins > Add New" from the left-hand menu.
3.  Click on the "Upload" option, then click "Choose File" to select the zip file from your computer. Once selected, press "OK" and press the "Install Now" button.
4.  Activate the plugin.
5.  Open the Settings page for WooCommerce and click the "Buyte" tab.
7.  Configure your "Buyte" settings. See below for details.

= Configure the plugin =
To configure the plugin, go to __WooCommerce > Settings__ from the left-hand menu, then click "Buyte" from the top tab menu.

* __Enable/Disable__ - check the box to enable Buyte.
* __API Endpoint__ - enter your Buyte API endpoint after deploying Buyte to your cloud.
* __Widget Endpoint__ - enter your Buyte Widget JS endpoint after deploying the Buyte Checkout.
* __Checkout Widget ID__   - enter your Buyte Checkout Widget Id.
* __Public Key__   - enter your Buyte Public Key.
* __Secret Key__   - enter your Buyte Secret Key.
* __Dark Background__ - check whether to render buttons on dark background
* __Log Message level__   - select the logging level.
* __Display on Checkout Page__ - Enables Buyte Checkout widget on Checkout Page
* __Display on Cart Page__ - Enables Buyte Checkout widget on Cart Page
* __Display on Product Page__ - Enables Buyte Checkout widget on Product Page
* Click on __Save Changes__ for the changes you made to be effected.