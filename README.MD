## CHANGE 1.2.9

can you check and suggest to me, for the subscription card payment, can  we directly send to add card page rather than displaying payment channel list?

============================================================================================================================
---

Configuring a hosted payment page integration.

I'll check Fiuu's documentation for how to open the card page directly, skipping the payment channel list.

Read 2 pages, searched the web, ran 4 commands

Reading github.com https://github.com/FiuuPayment/Cheatsheet-BestPractices-Fiuu_API
Stopped

Results

fiuu_mobile_xdk_flutter - pub.dev
fiuu_mobile_xdk_flutter - Dart API docs - pub.dev
[Official API] Fiuu API Spec for Merchant (v13.90) - www.scribd.com
Request Parameters - Payment Gateway - GitBook - e2payprod.gitbook.io
Hosted Integration APIs - Payment Gateway - GitBook - e2payprod.gitbook.io
GitHub - FiuuPayment/Cheatsheet-BestPractices-Fiuu_API: This is a cheatsheet for those developers who is frustrated by the lengthy official docs. · - GitHub github.com
Fiuu - documentation.ixopay.com
Merchant Request APIs  - e2payprod.gitbook.io
Seamless Integration - fiuu.com

Downloading Fiuu's official merchant API spec from GitHub to check the exact hosted-page options:

```bash
cd /tmp/claude-0/-home-claude/23d1018d-931b-5100-8a14-cfcadea51865/scratchpad && gh api repos/FiuuPayment/Documentation-Fiuu_API_Spec/contents --jq '.[].name' 2>&1 | head -20

*Response*
{"message":"GitHub access to this repository is not enabled for this session. Use add_repo to request access. If add_repo answers that read access is already available and you need GitHub API or write access, call add_repo again with access:\"push\" to attach the repository with credentials.","documentation_url":"https://docs.anthropic.com/en/docs/claude-code/github-actions"}gh: GitHub access to this repository is not enabled for this session. Use add_repo to request access. If add_repo answers that read access is already available and you need GitHub API or write access, call add_repo again with access:"push" to attach the repository with credentials. (HTTP 403)
```

Shell cwd was reset to /home/claude

```bash
cd /tmp/claude-0/-home-claude/23d1018d-931b-5100-8a14-cfcadea51865/scratchpad && for n in "%5Bofficial%20API%5D%20Fiuu%20API%20Spec%20for%20Merchant%20(v13.93).pdf" "%5Bofficial%20API%5D%20Fiuu%20API%20Spec%20for%20Merchant%20%28v13.93%29.pdf"; do curl -sSL -o spec.pdf "https://raw.githubusercontent.com/FiuuPayment/Documentation-Fiuu_API_Spec/main/$n" -w "%{http_code} %{size_download}\n"; file spec.pdf | grep -q PDF && break; done; file spec.pdf

*Response*
404 14
404 14
spec.pdf: ASCII text, with no line terminators
```

Shell cwd was reset to /home/claude

```bash
cd /tmp/claude-0/-home-claude/23d1018d-931b-5100-8a14-cfcadea51865/scratchpad && curl -sSL "https://github.com/FiuuPayment/Documentation-Fiuu_API_Spec" -o repo.html -w "%{http_code}\n"; grep -o 'blob/[^"]*\.pdf' repo.html | sort -u | head -20

*Response*
403
```

Shell cwd was reset to /home/claude
Locating the merchant API specification document online.

```bash
cd /tmp/claude-0/-home-claude/23d1018d-931b-5100-8a14-cfcadea51865/scratchpad && for b in master main; do for v in 13.93 13.92 13.91 13.90; do for pre in "%5Bofficial%20API%5D" "%5BOfficial%20API%5D"; do u="https://raw.githubusercontent.com/FiuuPayment/Documentation-Fiuu_API_Spec/$b/$pre%20Fiuu%20API%20Spec%20for%20Merchant%20(v$v).pdf"; c=$(curl -sSL -o spec.pdf -w "%{http_code}" "$u"); [ "$c" = 200 ] && echo "$c $u" && break 3; done; done; done; file spec.pdf

*Response*
spec.pdf: ASCII text, with no line terminators
```

Shell cwd was reset to /home/claude
The full spec file name isn't guessable, so I'll check Fiuu's published hosted-integration parameter reference instead:

Read e2payprod.gitbook.io - https://e2payprod.gitbook.io/payment-gateway/api-documentation/technical-doc-of-fiuu-id/payment-request-parameters/hosted-integration/request-parameters
Read e2payprod.gitbook.io - https://e2payprod.gitbook.io/payment-gateway/api-documentation/technical-doc-of-fiuu-id/hosted-integration-apis

Working out the correct payment gateway URL format.
Recalling old channel filename conventions for the gateway.
Spotting a missing index.php segment in the URL.
Planning a quick browser test before any code patch.

---

Yes. Most likely the only thing missing is /index.php in the payment link.

**Why you still see the channel list:** the app already asks for card-only (channel=credit). But Fiuu's hosted-integration docs say the channel has to be given in one of two ways:

1. as the channel's file name in the URL path: …/RMS/pay/MerchantID/<channel file>;
2. or as a channel parameter sent to index.php.

Your current link is https://pay.fiuu.com/RMS/pay/paynwash02?…&channel=credit. That's neither form, so Fiuu ignores the channel and shows the full list.

**Test it in 1 minute before I change any code.** Start a subscription so you get a fresh payment link, then copy it and insert /index.php after paynwash02:

```
Current: https://pay.fiuu.com/RMS/pay/paynwash02?orderid=…&channel=credit&…
Test:    https://pay.fiuu.com/RMS/pay/paynwash02/index.php?orderid=…&channel=credit&…
```

# Open the test link in a browser. 

Keep every other part of the link exactly as it is, because the security code (vcode) is tied to the order number and amount. You don't need to pay; just see which page opens.

- **If it goes straight to the card details form**, that's the fix. I'll change the link builder so subscription sign-up, upgrade and card update all open the card form directly. Bookings and bag purchases keep their full channel list. It's a small backend patch with no app rebuild.
- **If it still shows the list**, ask Fiuu support for paynwash02's card "channel filename" for the hosted page. Their docs mention a channel-list table that isn't in the public excerpt. Send me the name and I'll build the link as …/paynwash02/<filename> instead.

# Other options (I don't recommend these now):

- **Fiuu's mobile SDK (fiuu_mobile_xdk_flutter):** opens a native card form inside the app, but means bigger app changes. We parked it for iOS earlier.
- **Your own card form:** taking card numbers in your app or backend requires PCI-DSS compliance. Avoid it; the hosted page keeps card data away from your servers.
Try the /index.php link and tell me which page opens, and I'll send the patch.

# Sources:

Fiuu hosted integration: payment URL and channel selection - https://e2payprod.gitbook.io/payment-gateway/api-documentation/technical-doc-of-fiuu-id/hosted-integration-apis
Fiuu hosted integration: request parameters (channel) - https://e2payprod.gitbook.io/payment-gateway/api-documentation/technical-doc-of-fiuu-id/payment-request-parameters/hosted-integration/request-parameters
fiuu_mobile_xdk_flutter - https://pub.dev/packages/fiuu_mobile_xdk_flutter

---

=================================================================================================================================

can you create the test link with the dummy data, because the test link you provide got error Sorry
Your payment info format is not correct.

=================================================================================================================================

Trying different payment channel options to fix the error.
Thought for 7s

Building sample payment links with test verification codes.
Trying different payment channel options to fix the error.

I can compute a valid link myself. The security code (vcode) is md5(amount + merchant ID + order ID + verify key), using paynwash02's verify key from your .env. Your edited link probably failed because changing anything except the path breaks that code. I'll generate a few variants with dummy data, each with a correct code:

Generate signed Fiuu test links with dummy data

```python
python3 - <<'EOF'
import hashlib, urllib.parse
mid='paynwash02'; vk='d13a34f7e22dadd1f50eee901f471707'
amount='1.10'; oid='CARDTEST1003'
vcode=hashlib.md5((amount+mid+oid+vk).encode()).hexdigest()
base=dict(orderid=oid, amount=amount, bill_name='Test Customer', bill_email='test@automaid.asia', bill_mobile='60123456789', bill_desc='Card page test', currency='MYR')
def url(path, extra=None):
    q=dict(base); 
    if extra: q.update(extra)
    q['vcode']=vcode
    return f"https://pay.fiuu.com/RMS/pay/{mid}{path}?"+urllib.parse.urlencode(q)
print("A (current style):\n"+url('', {'channel':'credit'})+"\n")
print("B (index.php + channel):\n"+url('/index.php', {'channel':'credit'})+"\n")
print("C (channel file in path):\n"+url('/credit.php')+"\n")
print("D (trailing slash + channel):\n"+url('/', {'channel':'credit'}))
EOF

*Response*
A (current style):
https://pay.fiuu.com/RMS/pay/paynwash02?orderid=CARDTEST1003&amount=1.10&bill_name=Test+Customer&bill_email=test%40automaid.asia&bill_mobile=60123456789&bill_desc=Card+page+test&currency=MYR&channel=credit&vcode=52ef03f9d295e64c4535ff893802de13

B (index.php + channel):
https://pay.fiuu.com/RMS/pay/paynwash02/index.php?orderid=CARDTEST1003&amount=1.10&bill_name=Test+Customer&bill_email=test%40automaid.asia&bill_mobile=60123456789&bill_desc=Card+page+test&currency=MYR&channel=credit&vcode=52ef03f9d295e64c4535ff893802de13

C (channel file in path):
https://pay.fiuu.com/RMS/pay/paynwash02/credit.php?orderid=CARDTEST1003&amount=1.10&bill_name=Test+Customer&bill_email=test%40automaid.asia&bill_mobile=60123456789&bill_desc=Card+page+test&currency=MYR&vcode=52ef03f9d295e64c4535ff893802de13

D (trailing slash + channel):
https://pay.fiuu.com/RMS/pay/paynwash02/?orderid=CARDTEST1003&amount=1.10&bill_name=Test+Customer&bill_email=test%40automaid.asia&bill_mobile=60123456789&bill_desc=Card+page+test&currency=MYR&channel=credit&vcode=52ef03f9d295e64c4535ff893802de13
```

---

I generated four signed test links with dummy data: RM1.10, order CARDTEST1003. I haven't been able to open them from here, so I don't know yet which one skips the channel list. Don't complete a payment on any of them; just see which page opens.

# A: how the app builds the link now. 

Use this one first. It should show the channel list, as you've been seeing. If A also says "payment info format is not correct", the problem is the verify key, not the path, so tell me.

https://pay.fiuu.com/RMS/pay/paynwash02?orderid=CARDTEST1003&amount=1.10&bill_name=Test+Customer&bill_email=test%40automaid.asia&bill_mobile=60123456789&bill_desc=Card+page+test&currency=MYR&channel=credit&vcode=52ef03f9d295e64c4535ff893802de13


# B: /index.php plus the card channel

https://pay.fiuu.com/RMS/pay/paynwash02/index.php?orderid=CARDTEST1003&amount=1.10&bill_name=Test+Customer&bill_email=test%40automaid.asia&bill_mobile=60123456789&bill_desc=Card+page+test&currency=MYR&channel=credit&vcode=52ef03f9d295e64c4535ff893802de13


# C: card channel file in the path

https://pay.fiuu.com/RMS/pay/paynwash02/credit.php?orderid=CARDTEST1003&amount=1.10&bill_name=Test+Customer&bill_email=test%40automaid.asia&bill_mobile=60123456789&bill_desc=Card+page+test&currency=MYR&vcode=52ef03f9d295e64c4535ff893802de13


# D: trailing slash plus the card channel

https://pay.fiuu.com/RMS/pay/paynwash02/?orderid=CARDTEST1003&amount=1.10&bill_name=Test+Customer&bill_email=test%40automaid.asia&bill_mobile=60123456789&bill_desc=Card+page+test&currency=MYR&channel=credit&vcode=52ef03f9d295e64c4535ff893802de13


Open each in a normal browser and tell me what you see for A, B, C and D: the channel list, the card details form, or an error. I'll build the link the way that goes straight to the card form, for subscription payments only.

If none of them opens the card form directly, ask Fiuu support: "What is the hosted payment page channel filename/URL to open the credit card form directly for merchant paynwash02?"

These links contain no customer data and won't affect your database. If Fiuu calls your webhook for CARDTEST1003, the server just logs that there's no such order.

---
=====================================================================================================================================

i'm ammend this .env using this merchant, 

RECURRING_MERCHANT_ID=paynwash
RECURRING_VERIFY_KEY=a7d5c17a452f99b7e03447631d05aae5
RECURRING_SECRET_KEY=c3838d2bddbc973d85a19ec830e4df76
can you redo the test link above.

=====================================================================================================================================

Regenerate signed test links for merchant paynwash

```python
python3 - <<'EOF'
import hashlib, urllib.parse
mid='paynwash'; vk='a7d5c17a452f99b7e03447631d05aae5'
amount='1.10'; oid='CARDTEST1004'
vcode=hashlib.md5((amount+mid+oid+vk).encode()).hexdigest()
base=dict(orderid=oid, amount=amount, bill_name='Test Customer', bill_email='test@automaid.asia', bill_mobile='60123456789', bill_desc='Card page test', currency='MYR')
def url(path, extra=None):
    q=dict(base)
    if extra: q.update(extra)
    q['vcode']=vcode
    return f"https://pay.fiuu.com/RMS/pay/{mid}{path}?"+urllib.parse.urlencode(q)
for k,(p,e) in {'A':('',{'channel':'credit'}),'B':('/index.php',{'channel':'credit'}),'C':('/credit.php',None),'D':('/',{'channel':'credit'})}.items():
    print(k, url(p,e))
EOF

*Response*
A https://pay.fiuu.com/RMS/pay/paynwash?orderid=CARDTEST1004&amount=1.10&bill_name=Test+Customer&bill_email=test%40automaid.asia&bill_mobile=60123456789&bill_desc=Card+page+test&currency=MYR&channel=credit&vcode=25b457694362b9edd9d05b12dde22257
B https://pay.fiuu.com/RMS/pay/paynwash/index.php?orderid=CARDTEST1004&amount=1.10&bill_name=Test+Customer&bill_email=test%40automaid.asia&bill_mobile=60123456789&bill_desc=Card+page+test&currency=MYR&channel=credit&vcode=25b457694362b9edd9d05b12dde22257
C https://pay.fiuu.com/RMS/pay/paynwash/credit.php?orderid=CARDTEST1004&amount=1.10&bill_name=Test+Customer&bill_email=test%40automaid.asia&bill_mobile=60123456789&bill_desc=Card+page+test&currency=MYR&vcode=25b457694362b9edd9d05b12dde22257
D https://pay.fiuu.com/RMS/pay/paynwash/?orderid=CARDTEST1004&amount=1.10&bill_name=Test+Customer&bill_email=test%40automaid.asia&bill_mobile=60123456789&bill_desc=Card+page+test&currency=MYR&channel=credit&vcode=25b457694362b9edd9d05b12dde22257
```

---

Here are the four links again, signed with the paynwash account: dummy data, RM1.10, order CARDTEST1004. Don't complete a payment; just see which page opens.

# A: how the app builds the link now. 

Use this one first. If this one also says "payment info format is not correct", the verify key doesn't match the paynwash account.

https://pay.fiuu.com/RMS/pay/paynwash?orderid=CARDTEST1004&amount=1.10&bill_name=Test+Customer&bill_email=test%40automaid.asia&bill_mobile=60123456789&bill_desc=Card+page+test&currency=MYR&channel=credit&vcode=25b457694362b9edd9d05b12dde22257


# B: /index.php plus the card channel

https://pay.fiuu.com/RMS/pay/paynwash/index.php?orderid=CARDTEST1004&amount=1.10&bill_name=Test+Customer&bill_email=test%40automaid.asia&bill_mobile=60123456789&bill_desc=Card+page+test&currency=MYR&channel=credit&vcode=25b457694362b9edd9d05b12dde22257


# C: card channel file in the path

https://pay.fiuu.com/RMS/pay/paynwash/credit.php?orderid=CARDTEST1004&amount=1.10&bill_name=Test+Customer&bill_email=test%40automaid.asia&bill_mobile=60123456789&bill_desc=Card+page+test&currency=MYR&vcode=25b457694362b9edd9d05b12dde22257


# D: trailing slash plus the card channel

https://pay.fiuu.com/RMS/pay/paynwash/?orderid=CARDTEST1004&amount=1.10&bill_name=Test+Customer&bill_email=test%40automaid.asia&bill_mobile=60123456789&bill_desc=Card+page+test&currency=MYR&channel=credit&vcode=25b457694362b9edd9d05b12dde22257


Tell me for A, B, C and D whether you see the channel list, the card form, or an error.

# Two things about switching to paynwash:

- **Refresh Laravel's cache:** run php artisan optimize:clear so it picks up the new .env values. Otherwise the app keeps using paynwash02.
- **Earlier test subscriptions:** any card saved under paynwash02 stays tied to paynwash02. After this switch the renewal job would treat it as your normal Fiuu account and the charge would fail. If you made test subscriptions under paynwash02, cancel them and subscribe again under paynwash.

These verify and secret keys are now in this chat. They're live account keys, so regenerate them in the Fiuu portal once testing is done.

---

===============================================================================================================================================

results all the same, still open payment channel API page. but when i click card and click on pay button, it display add card page this link https://pay.fiuu.com/MOLPay/API/seamless/index.php

===============================================================================================================================================

Verifying the signature calculation and submission endpoint.
Thought for 8s

Investigating how the payment integration's card-add flow works.
Mapping the exact fields the seamless endpoint expects.
Verifying the signature calculation and submission endpoint.

The card form is Fiuu's Seamless page (/MOLPay/API/seamless/index.php). The hosted page simply forwards to it after you pick Card. If your backend sends the customer straight to that page with the card channel, the channel list is skipped. I'll look up Fiuu's Seamless request format so I can build the link correctly:

Read 2 pages, searched the web

Results
Seamless Integration - fiuu.com
GitHub - radzijs/Maybank2u-Seamless-Integration: Maybank2u Seamless Integration - github.com
GitHub - vespazwan/FB-App-Seamless: MOLPay FB App Seamless Integration - github.com
GitHub - radzijs/epay-Seamless-Integration - github.com
Seamless Integration APIs - e2payprod.gitbook.io
Razer Merchant Services Seamless Integration v3.28 (non PCI) - github.com
Seamless Integration - merchant.razer.com
GitHub - FiuuPayment/Cheatsheet-BestPractices-Fiuu_API: This is a cheatsheet for those developers who is frustrated by the lengthy official docs. · GitHub - github.com
Razer Merchant Services Seamless Integration v3.3 (non PCI) - RazerMS/Integration-RazerMS_JavaScript_Seamless_Integration GitHub Wiki - github-wiki-see.page
Razer Merchant Services Seamless Integration v3.26 (non PCI) - RazerMS/Integration-RazerMS_JavaScript_Seamless_Integration GitHub Wiki - github-wiki-see.page

Read fiuu.com - https://fiuu.com/seamless-integration/
Read github-wiki-see.page - https://github-wiki-see.page/m/RazerMS/Integration-RazerMS_JavaScript_Seamless_Integration/wiki/Razer-Merchant-Services-Seamless-Integration-v3.26-(non-PCI)

Tracing how the seamless payment integration triggers requests.
Designing a backend-hosted page to drive the seamless flow.
Drafting a test page to verify the required payment parameters.

---

Fiuu's Seamless script normally builds this request. Downloading the script to see exactly what it sends to seamless/index.php, so the test isn't guesswork:

Shared a file, read a page, ran 3 commands

```bash
cd /tmp/claude-0/-home-claude/23d1018d-931b-5100-8a14-cfcadea51865/scratchpad && for u in "https://pay.fiuu.com/RMS/API/seamless/3.28/js/MOLPay_seamless.deco.js" "https://www.onlinepayment.com.my/RMS/API/seamless/3.28/js/MOLPay_seamless.deco.js" "https://raw.githubusercontent.com/FiuuPayment/Integration-Fiuu_JavaScript_Seamless_Integration/master/README.md" "https://raw.githubusercontent.com/RazerMS/Integration-RazerMS_JavaScript_Seamless_Integration/master/README.md"; do c=$(curl -sSL -o f.tmp -w "%{http_code} %{size_download}" "$u"); echo "$c $u"; done

*Response*
curl: (56) CONNECT tunnel failed, response 403
000 0 https://pay.fiuu.com/RMS/API/seamless/3.28/js/MOLPay_seamless.deco.js
curl: (56) CONNECT tunnel failed, response 403
000 0 https://www.onlinepayment.com.my/RMS/API/seamless/3.28/js/MOLPay_seamless.deco.js
200 3063 https://raw.githubusercontent.com/FiuuPayment/Integration-Fiuu_JavaScript_Seamless_Integration/master/README.md
200 597 https://raw.githubusercontent.com/RazerMS/Integration-RazerMS_JavaScript_Seamless_Integration/master/README.md
```

Shell cwd was reset to /home/claude

```bash
cd /tmp/claude-0/-home-claude/23d1018d-931b-5100-8a14-cfcadea51865/scratchpad && curl -sSL "https://raw.githubusercontent.com/FiuuPayment/Integration-Fiuu_JavaScript_Seamless_Integration/master/README.md"; for f in "process_order.php" "index.html" "example/process_order.php" "example/index.html" "Example/process_order.php"; do c=$(curl -sSL -o "x_$(basename $f)" -w "%{http_code}" "https://raw.githubusercontent.com/FiuuPayment/Integration-Fiuu_JavaScript_Seamless_Integration/master/$f"); echo "$c $f"; done

*Response*
# [Integration] – Fiuu JavaScript Seamless Integration

<img src="https://user-images.githubusercontent.com/38641542/74420622-b6579a00-4e86-11ea-838d-f7d741743d0f.jpg">


Introduction
-----

Fiuu Seamless Integration offers better UX/UI during checkout to increase your conversion rate and reduce abandon or drop off rate. Buyer just need to click to popup the bank login page to proceed and authorize the payment without redirecting to Fiuu hosted payment page (better UX with lesser checkout step). Merchant or seller may customize their own look-and-feel on their checkout page (better and flexible UI).

Fiuu Seamless Integration developed by Fiuu technical team.


Fiuu无缝整合
=====================

Fiuu无缝整合为商家提供了更好的用户体验和使用者界面，用户只须点击银行LOGO按钮就可以弹出登入界面，然后便可以直接登入应允支付，整个购买支付流程更快捷方便，界面也可以由商家自订，已被许多成功商家证实为非常简易且灵活的支付整合方式。


Notes / Prerequisite
-----

Fiuu is not responsible for any problems that might arise from the use of this module. 
Use at your own risk. Please backup any critical data before proceeding. For any query or 
assistance, please email to support@fiuu.com 


Installations Guidance for Seamless 
--------------------------

[FB APP Seamless Integration](https://github.com/FiuuPayment/Integration-Fiuu_JavaScript_Seamless_Integration/wiki/Fiuu-FB-App-Seamless-Integration)

Seamless Integration (non-PCI):
- [Latest Version (Auto update)](https://github.com/FiuuPayment/Integration-Fiuu_JavaScript_Seamless_Integration/wiki/Fiuu-Seamless-Integration-v3.28-(non-PCI)) **Update: 12/03/2025** - AlipayPlus
- [Version 3.28](https://github.com/FiuuPayment/Integration-Fiuu_JavaScript_Seamless_Integration/wiki/Fiuu-Seamless-Integration-v3.28-(non-PCI))
- [Seamless for RMS Indonesia](https://github.com/FiuuPayment/Integration-Fiuu_JavaScript_Seamless_Integration/wiki/Fiuu-Seamless-Integration-for-RMS-Indonesia-(non-PCI)) (e2Pay)


## Resources
- GitHub:     https://github.com/FiuuPayment
- Website:    https://fiuu.com/
- Twitter:    https://twitter.com/FiuuPayment
- YouTube:    https://www.youtube.com/c/FiuuPayment
- Facebook:   https://www.facebook.com/FiuuPayment/
- Instagram:  https://www.instagram.com/FiuuPayment/



Known Issues
------------
- From Seamless Integration v3.12(non-PCI) -> Latest

	- Payment made from in-app browser (IAB) at Maybank2u, CIMB Clicks, Hong Leong Connect and AmBank will not return to the merchant website.

Submit issue to this repository or email to our support@fiuu.com


## Support

Submit issue to this repository or email to our support@fiuu.com

Merchant Technical Support / Customer Care : support@fiuu.com<br>
Sales/Reseller Enquiry : sales@fiuu.com<br>
Marketing Campaign : marketing@fiuu.com<br>
Channel/Partner Enquiry : channel@fiuu.com<br>
Media Contact : media@fiuu.com<br>
R&D and Tech-related Suggestion : technical@fiuu.com<br>
Abuse Reporting : abuse@fiuu.com
404 process_order.php
404 index.html
404 example/process_order.php
404 example/index.html
404 Example/process_order.php
```

Shell cwd was reset to /home/claude

Read github-wiki-see.page - https://github-wiki-see.page/m/FiuuPayment/Integration-Fiuu_JavaScript_Seamless_Integration/wiki/Fiuu-Seamless-Integration-v3.28-(non-PCI)

Setting up a payment page with auto-triggered seamless checkout.
Checking whether popups work inside the mobile app's webview.
Drafting two test files to compare checkout trigger methods.
Drafting a test page to compare Fiuu checkout methods

```bash
mkdir -p /home/claude/out && python3 - <<'EOF'
import hashlib, html
mid='paynwash'; vk='a7d5c17a452f99b7e03447631d05aae5'
amount='1.10'; oid='CARDTEST1005'
vcode=hashlib.md5((amount+mid+oid+vk).encode()).hexdigest()
p=dict(mpsmerchantid=mid, mpschannel='credit', mpsamount=amount, mpsorderid=oid, mpsbill_name='Test Customer',
       mpsbill_email='test@automaid.asia', mpsbill_mobile='60123456789', mpsbill_desc='Card page test',
       mpscountry='MY', mpscurrency='MYR', mpslangcode='en', mpsvcode=vcode, mpstokenstatus='1')
attrs=' '.join(f'data-{k}="{html.escape(v)}"' for k,v in p.items())
fields='\n'.join(f'    <input type="hidden" name="{k}" value="{html.escape(v)}">' for k,v in p.items())
page=f'''<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fiuu card page test</title>
<style>body{{font-family:system-ui,sans-serif;max-width:480px;margin:24px auto;padding:0 16px;line-height:1.5}}
button{{display:block;width:100%;padding:14px;margin:12px 0;font-size:16px;border-radius:8px;border:0;color:#fff;background:#1565C0}}
.b2{{background:#555}} small{{color:#666}}</style>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://pay.fiuu.com/RMS/API/seamless/3.28/js/MOLPay_seamless.deco.js"></script>
</head><body>
<h3>Fiuu card form test (merchant {mid})</h3>
<p><small>Dummy order {oid}, RM{amount}. Do NOT complete payment, just see which page opens.</small></p>

<p><b>Test 1 – official Seamless script</b></p>
<button type="button" id="pay1" data-toggle="molpayseamless" {attrs}>Test 1: Pay by card</button>

<p><b>Test 2 – direct post to seamless page</b></p>
<form method="POST" action="https://pay.fiuu.com/MOLPay/API/seamless/index.php">
{fields}
    <button class="b2" type="submit">Test 2: Pay by card</button>
… (+6 more rows)

*Response*
94fd1b2ac3609f9a7e95fb19940544db
```

---

Your test answers it. Every link variant still shows the channel list, so Fiuu's hosted payment page always shows the list, whatever we put in the link. The add-card form you reached after choosing Card is Fiuu's separate Seamless checkout (/MOLPay/API/seamless/index.php). Fiuu's Seamless docs confirm it can open the card form directly with channel credit. It also accepts mpstokenstatus=1, which asks Fiuu to save the card for monthly renewals.

**One more quick test before I build it.** In fiuu_card_test.html, both buttons open the same dummy RM1.10 order, CARDTEST1005, on paynwash. Don't complete the payment.

1. Download the file, open it in Chrome on your phone or PC, and allow pop-ups if asked.
2. Tap Test 1 (Fiuu's official Seamless script).
3. Go back and tap Test 2 (posts straight to the seamless page).

Tell me for each button whether it goes straight to the add-card form, shows the channel list, or gives an error.

# What I'll build once one works:

- **Backend:** a small checkout page at /payment/fiuu/card/{order}. The backend works out the security code, so the verify key never reaches the phone. Subscription sign-up, upgrade and card update then open this page instead of the hosted channel list. Bookings and bag purchases keep the full channel list.

- **App:** nothing changes. It already opens whatever payment link the backend returns, and detects success the same way.

If neither button skips the list, card-only for this merchant needs Fiuu to set it up. Ask support@fiuu.com: "Please enable direct credit card (channel credit) via seamless/hosted page for merchant paynwash, with tokenization."

# Sources:

Fiuu Seamless Integration v3.28 (parameters, credit channel, mpstokenstatus) - https://github-wiki-see.page/m/FiuuPayment/Integration-Fiuu_JavaScript_Seamless_Integration/wiki/Fiuu-Seamless-Integration-v3.28-(non-PCI)
Fiuu JavaScript Seamless Integration (GitHub) - https://github.com/FiuuPayment/Integration-Fiuu_JavaScript_Seamless_Integration
Fiuu hosted integration: payment URL and channel - https://e2payprod.gitbook.io/payment-gateway/api-documentation/technical-doc-of-fiuu-id/hosted-integration-apis

Fiuu card test
Code·HTML 

---

===============================================================================================================================================

test 2 open

===============================================================================================================================================

Securing signed URLs for recurring card payments.
Thought for 9s

Building a payment gateway integration for card processing.
Mapping seamless redirect fields to payment callback routes.
Securing signed URLs for recurring card payments.

Test 2 works, so we can send customers straight to the card form by posting to Fiuu's seamless page. Before building, I need to check how the app's payment screen decides the payment has finished. The new page will live on your own domain, and I don't want the app to mistake it for the finish page.

Shared a file, ran 10 commands

```bash
cd /home/claude/orig/customer/srv/www/automaid/automaid_customer/automaid_customer_scaffold/lib/features/customer/payment && grep -n "return\|webhook\|contains\|startsWith\|NavigationDecision\|onPageFinished\|onNavigationRequest\|pop(" payment_webview_screen.dart | head -40

*Response*
5:/// completes by watching for navigation to the backend's own return
6:/// route (POST /webhook/fiuu/return — see routes/web.php). That page is
27:  /// Matches any of the backend webhook endpoints either gateway might
28:  /// land on after payment (return/notification/callback all live
29:  /// under 'webhook' — see routes/web.php's 'webhook' group). Checking
33:  /// matching '/webhook/fiuu/' meant a GKash payment would never be
35:  bool _isReturnUrl(String url) => url.contains('/webhook/fiuu/') || url.contains('/webhook/gkash/');
48:              // (and the webhook a moment to finish processing) before
52:                if (mounted) Navigator.of(context).pop(true);
57:          onPageFinished: (url) {
67:    return PopScope(
75:            onPressed: () => Navigator.of(context).pop(false),
```

Routing payment webhook paths away from a protected prefix.
Checking GKash routing to mirror it for the new page

```bash
cd /home/claude/rebase/srv/www/html && sed -n 20,60p routes/web.php; ls resources/views/payment/

*Response*
// Route::get('email/resend', [AuthController::class, 'resend'])->name('verification.resend');
Route::get('send/sms', [TestController::class, 'sendSms'])->name('send.sms');


// webhook
Route::group([
    'prefix' => 'webhook',
    'as' => 'webhook.'
], function() {
    Route::post('/fiuu/return', [FiuuController::class, 'getReturn'])->name('fiuu.return');
    Route::post('/fiuu/notification', [FiuuController::class, 'getNotification'])->name('fiuu.notification');
    Route::post('/fiuu/callback', [FiuuController::class, 'getCallback'])->name('fiuu.callback');

    Route::post('/gkash/return', [GkashController::class, 'getReturn'])->name('gkash.return');
    Route::post('/gkash/notification', [GkashController::class, 'getNotification'])->name('gkash.notification');
    Route::get('/gkash/checkout-form/{orderid}', [GkashController::class, 'getCheckoutForm'])->name('gkash.checkout-form');
});

Route::get('/qrcode/print/{series_no}', function ($series_no) {
    $record = Qrcode::where('series_no', $series_no)->firstOrFail();
    return view('qr-modal', [
        'qrCode' => $record->series_no,
    ]);
})->name('qrcode.print');

// Serves the T&C PDF server-side rather than requiring the S3 object to
// be public — see PublicDocumentController's doc comment for why.
Route::get('/documents/terms-conditions', [\App\Http\Controllers\PublicDocumentController::class, 'termsConditions'])
    ->name('documents.terms-conditions');

// Same reasoning, for order handoff photos (rider pickup, merchant
// wash, delivery, etc.) — same S3 bucket, same Block Public Access
// restriction.
Route::get('/documents/step-photo/{hashslug}', [\App\Http\Controllers\PublicDocumentController::class, 'stepPhoto'])
    ->name('documents.step-photo');
Route::get('/documents/pickup-photo/{hashslug}', [\App\Http\Controllers\PublicDocumentController::class, 'pickupPhoto'])
    ->name('documents.pickup-photo');
Route::get('/documents/landmark-picture/{hashslug}', [\App\Http\Controllers\PublicDocumentController::class, 'landmarkPicture'])
    ->name('documents.landmark-picture');
Route::get('/documents/banner-image/{hashslug}', [\App\Http\Controllers\PublicDocumentController::class, 'bannerImage'])
return.blade.php
```

Integrating payment channel logic for recurring accounts.
Assembling the seamless form fields for card payments.
Wiring up verification codes and routing for the checkout endpoint.
Building a direct card checkout page for recurring subscriptions

```python
python3 - <<'EOF'
p='app/Services/PaymentGateway/FiuuPaymentService.php'
s=open(p).read()
def rep(o,n):
    global s
    assert s.count(o)==1,o[:60]; s=s.replace(o,n)
rep("""    public function getPaymentUrl(array $data)
    {
        $rms = new Payment($this->merchantId, $this->verifyKey, $this->secretKey, $this->environment);""",
"""    public function getPaymentUrl(array $data)
    {
        // Subscription card payments (recurring account, channel
        // "credit"): Fiuu's hosted page always shows the channel list,
        // even with channel=credit. Send the customer to our own small
        // page instead, which posts straight to Fiuu's seamless card
        // form (see seamlessCardForm()). Signed + expiring link.
        if (($data['channel'] ?? null) === 'credit' && $this->isRecurringAccount()) {
            return \\Illuminate\\Support\\Facades\\URL::temporarySignedRoute(
                'payment.fiuu.card',
                now()->addMinutes(60),
                ['order' => $data['orderid']]
            );
        }

        $rms = new Payment($this->merchantId, $this->verifyKey, $this->secretKey, $this->environment);""")
rep("""    public function getMerchantId(): ?string
    {
        return $this->merchantId;
    }""","""    public function getMerchantId(): ?string
    {
        return $this->merchantId;
    }

    public function isRecurringAccount(): bool
    {
        return $this->recMerchantId && $this->merchantId === $this->recMerchantId;
… (+160 more rows)

*Response*
6:    <title>{{ $success ? 'Payment successful' : 'Payment failed' }}</title>
37:        .icon.success { background: #2e7d32; }
54:        <div class="icon {{ $success ? 'success' : 'failure' }}">{{ $success ? '✓' : '✕' }}</div>
55:        <h1>{{ $title }}</h1>
```

Checking required payment callback parameters against documentation.
Weighing relative versus absolute URL signing for proxies.
Fixing signed links to ignore scheme mismatches behind Cloudflare

```python
python3 - <<'EOF'
def rep(p,o,n):
    s=open(p).read(); assert s.count(o)==1,(p,o[:50]); open(p,'w').write(s.replace(o,n))
rep('app/Services/PaymentGateway/FiuuPaymentService.php',"""            return \\Illuminate\\Support\\Facades\\URL::temporarySignedRoute(
                'payment.fiuu.card',
                now()->addMinutes(60),
                ['order' => $data['orderid']]
            );""","""            // Signature covers the path only ("signed:relative"), so it
            // still validates behind Cloudflare/proxies that change the
            // scheme or host the app server sees.
            return url(\\Illuminate\\Support\\Facades\\URL::temporarySignedRoute(
                'payment.fiuu.card',
                now()->addMinutes(60),
                ['order' => $data['orderid']],
                false
            ));""")
rep('routes/web.php',"    ->middleware('signed')\n    ->name('payment.fiuu.card');","    ->middleware('signed:relative')\n    ->name('payment.fiuu.card');")
EOF
php -l app/Services/PaymentGateway/FiuuPaymentService.php | grep -v "No syntax"; echo ok

*Response*
ok
```

Running end-to-end tests on payment link routes

```bash
cd /home/claude/apptest && for f in app/Services/PaymentGateway/FiuuPaymentService.php app/Http/Controllers/Payment/FiuuCardCheckoutController.php resources/views/payment/fiuu-card.blade.php resources/views/payment/return.blade.php routes/web.php app/Http/Controllers/Webhook/FiuuController.php; do mkdir -p $(dirname $f); cp /home/claude/rebase/srv/www/html/$f $f; done; rm -f database/t.sqlite && touch database/t.sqlite && cat > t7.php <<'EOF'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{Schema,DB};
use Illuminate\Http\Request;
Illuminate\Database\Eloquent\Model::unsetEventDispatcher();
config(['app.url'=>'https://app.automaid.asia','services.fiuu.environment'=>'sandbox','services.fiuu.merchant_id'=>'oldmid','services.fiuu.verify_key'=>'OVK','services.fiuu.secret_key'=>'OSK',
 'services.recurring.rec_merchant_id'=>'paynwash','services.recurring.rec_verify_key'=>'a7d5c17a452f99b7e03447631d05aae5','services.recurring.rec_secret_key'=>'RSK','services.recurring.rec_base_url'=>'https://pay.fiuu.com']);
URL::forceRootUrl('https://app.automaid.asia');
foreach (['users'=>['name','email','mobile_no','password'],'orders'=>['user_id','order_type','status','grand_total','billing_name','billing_email','billing_phone']] as $t=>$c) Schema::create($t, function($b) use($c){ $b->id(); foreach($c as $x) $b->string($x)->nullable(); $b->timestamps(); $b->softDeletes(); });
DB::table('users')->insert(['id'=>7,'name'=>'Ariff','email'=>'a@b.my','mobile_no'=>'60127033845','password'=>'x']);
DB::table('orders')->insert([['id'=>1359,'user_id'=>7,'order_type'=>'subscription','status'=>'pending','grand_total'=>'50.00','billing_name'=>'ariff faisal','billing_email'=>'blinx@x.com','billing_phone'=>'60127033845'],
  ['id'=>1360,'user_id'=>7,'order_type'=>'subscription','status'=>'paid','grand_total'=>'50.00',null,null,null],['id'=>1361,'user_id'=>7,'order_type'=>'booking','status'=>'pending','grand_total'=>'20.00',null,null,null]]);
$sub = (new App\Services\PaymentGateway\PaymentGatewayResolver())->resolveForSubscription('fiuu');
$url = $sub->createPaymentUrl(['orderid'=>1359,'amount'=>'50.00','bill_name'=>'x','bill_email'=>'x','bill_mobile'=>'x','bill_desc'=>'Subscription','channel'=>'credit']);
echo "1 app gets: $url\n   app would treat as finished? ".((str_contains($url,'/webhook/fiuu/')||str_contains($url,'/webhook/gkash/'))?'YES (bad)':'no (good)').
… (last line cut; +31 more rows)

*Response*
1 app gets: https://app.automaid.asia/payment/fiuu/card/1359?expires=1791009952&signature=fa4c6094d89aced32c007f40023105b0a4e4d0d2a820c8fa38e36d920ee00904
   app would treat as finished? no (good)
2 page status: 500
   posts to: -
   ErrorException 
  Undefined array key "mpsmerchantid"
  at t7.php:23
     19▕ $hit = function($u, $proxyHttp=false) use ($kernel) { $r = Request::create($u, 'GET'); if ($proxyHttp) { $r = Request::create(str_replace('https://','http://',$u).'', 'GET'); } $resp = $kernel->handle($r); return [$resp->getStatusCode(), $resp->getContent()]; };
     20▕ [$code,$body] = $hit($url); echo "2 page status: $code\n";
     21▕ preg_match('/action="([^"]+)"/', $body, $m); echo "   posts to: ".html_entity_decode($m[1]??'-')."\n";
     22▕ preg_match_all('/name="(mps\w+)" value="([^"]*)"/', $body, $mm, PREG_SET_ORDER); $f=[]; foreach($mm as $x) $f[$x[1]]=html_entity_decode($x[2]);
  ➜  23▕ echo "   fields: ".implode(',', array_keys($f))."\n   merchant=$f[mpsmerchantid] channel=$f[mpschannel] amount=$f[mpsamount] order=$f[mpsorderid] token=$f[mpstokenstatus] return=$f[mpsreturnurl]\n";
     24▕ echo "   vcode ok: ".($f['mpsvcode']===md5('50.00'.'paynwash'.'1359'.'a7d5c17a452f99b7e03447631d05aae5')?'yes':'NO')." | verify key leaked in page: ".(str_contains($body,'a7d5c17a')?'YES':'no')."\n";
     25▕ [$code] = $hit($url, true); echo "3 behind proxy (seen as http): $code\n";
     26▕ [$code] = $hit(preg_replace('/card\/1359/','card/1361',$url)); echo "4 tampered order id: $code\n";
     27▕ [$code] = $hit(preg_replace('/signature=\w+/','signature=abc',$url)); echo "5 bad signature: $code\n";
  1   t7.php:23
      Illuminate\Foundation\Bootstrap\HandleExceptions::{closure:Illuminate\Foundation\Bootstrap\HandleExceptions::forwardsTo():257}()
```

```bash
sed -i 's/\[\$code,\$body\] = \$hit(\$url); echo "2 page status: \$code\\n";/[$code,$body] = $hit($url); echo "2 page status: $code\\n"; if ($code>=500) { echo substr(strip_tags($body),0,600),"\\n"; preg_match("\/\\"message\\":\\"([^\\"]+)\/", $body, $em); echo ($em[1]??""),"\\n"; exit; }/' t7.php && rm -f database/t.sqlite && touch database/t.sqlite && php t7.php 2>&1 | grep -v "^\s*$" | head -12; tail -30 storage/logs/laravel.log | grep -m3 -i "error\|exception" | cut -c1-400

*Response*
1 app gets: https://app.automaid.asia/payment/fiuu/card/1359?expires=1791009956&signature=68b856a155d3e05b4fd21efed9c846e0df984a2c09721eb8fd490e30970cc09b
   app would treat as finished? no (good)
2 page status: 500
        Server Error
            /*! normalize.css v8.0.1 | MIT License | github.com/necolas/normalize.css */html{line-height:1.15;-webkit-text-size-adjust:100%}body{margin:0}a{background-color:transparent}code{font-family:monospace,monospace;font-size:1em}[hidden]{display:none}html{font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,Noto Sans,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol,Noto Color Emoji;line-height:1.5}*,:after,:before{box-sizing:border-box;border:0 solid #e2e8f0}a{color:inherit;text-de
```

```bash
ls storage/logs/ 2>/dev/null; grep -h -m2 "local.ERROR" storage/logs/*.log 2>/dev/null | tail -2 | cut -c1-500

*Response*
laravel.log
```

```bash
grep -h "ERROR" storage/logs/laravel.log | tail -2 | cut -c1-600

*Response*
[2026-10-03 13:45:52] production.ERROR: Undefined array key "mpsmerchantid" {"exception":"[object] (ErrorException(code: 0): Undefined array key \"mpsmerchantid\" at /home/claude/apptest/t7.php:23)
[2026-10-03 13:45:56] production.ERROR: Connection refused [tcp://127.0.0.1:6379] {"exception":"[object] (Predis\\Connection\\ConnectionException(code: 111): Connection refused [tcp://127.0.0.1:6379] at /home/claude/apptest/vendor/predis/predis/src/Connection/AbstractConnection.php:132)
```

That's only the test environment: the web middleware tries to use Redis for sessions, and there's no Redis here. Switching the test to in-memory sessions and cache:

```bash
sed -i "s/config(\['app.url'=>/config(['session.driver'=>'array','cache.default'=>'array','queue.default'=>'sync','app.url'=>/" t7.php && rm -f database/t.sqlite && touch database/t.sqlite && php t7.php 2>&1 | grep -v "^\s*$" | head -16

*Response*
1 app gets: https://app.automaid.asia/payment/fiuu/card/1359?expires=1791009965&signature=7aa83149bf298e4cd79ff1c27e13b344ee693d268d58e807aaf839ecd92fe995
   app would treat as finished? no (good)
2 page status: 200
   posts to: https://pay.fiuu.com/MOLPay/API/seamless/index.php
   fields: mpsmerchantid,mpschannel,mpsamount,mpsorderid,mpsbill_name,mpsbill_email,mpsbill_mobile,mpsbill_desc,mpscountry,mpscurrency,mpslangcode,mpsvcode,mpstokenstatus,mpsreturnurl,mpsnotifyurl,mpscallbackurl
   merchant=paynwash channel=credit amount=50.00 order=1359 token=1 return=https://app.automaid.asia/webhook/fiuu/return
   vcode ok: yes | verify key leaked in page: no
3 behind proxy (seen as http): 200
4 tampered order id: 403
5 bad signature: 403
6 already-paid order: 410 (shows expired page)
7 bookings unchanged: https://sandbox-payment.fiuu.com/RMS/pay/oldmid
```

Confirming all verification and security tests pass

```bash
set -e; cd /home/claude/rebase/srv/www/html && git add -N app/Http/Controllers/Payment/FiuuCardCheckoutController.php resources/views/payment/fiuu-card.blade.php && git diff > /home/claude/out/automaid_fiuu_card_direct.patch && git diff --stat && rm -rf /home/claude/v12 && mkdir /home/claude/v12 && tar xzf /mnt/user-data/uploads/automaid-web_tar.gz -C /home/claude/v12 && cd /home/claude/v12/srv/www/html && git apply /home/claude/out/automaid_fiuu_subscription_renewal.patch 2>/dev/null && git apply /home/claude/out/automaid_fiuu_recurring_live_url.patch && git apply /home/claude/out/automaid_cancel_failed_checkouts.patch && git apply /home/claude/out/automaid_cancel_failed_checkouts_update.patch && git apply --check /home/claude/out/automaid_fiuu_card_direct.patch && echo APPLIES_ON_DEPLOYED_STATE; cd /home/claude && rm -rf v12; cd rebase/srv/www/html && git add -A && git commit -qm "local ref: fiuu direct card page (not pushed)"

*Response*
 .../Payment/FiuuCardCheckoutController.php         | 50 ++++++++++++++++++
 app/Services/PaymentGateway/FiuuPaymentService.php | 59 ++++++++++++++++++++++
 resources/views/payment/fiuu-card.blade.php        | 29 +++++++++++
 routes/web.php                                     |  7 +++
 4 files changed, 145 insertions(+)
APPLIES_ON_DEPLOYED_STATE
```

---

Subscription payments will now open Fiuu's add-card form directly, using the same seamless page your Test 2 opened. It's a backend-only patch. I tested it on a copy of the backend, not against Fiuu itself, so your phone test is the real check.

# How it works

1. When a customer subscribes, upgrades or updates their card, the app gets a link to a small new page: app.automaid.asia/payment/fiuu/card/{order}.
2. That page posts the order straight to Fiuu's card form.
3. Fiuu is asked to save the card with mpstokenstatus=1, so the monthly renewal can charge it.
4. After payment, Fiuu returns to /webhook/fiuu/return. The app already watches for that, so no app rebuild is needed.


# Security

- The security code is calculated on the server, so your verify key never appears in the page.
- The link expires after 60 minutes, and changing the order number in it is rejected (403).
- A link for an order that's already paid or cancelled shows "Payment link expired".

**Unchanged:** bookings and bag purchases still use Fiuu's normal payment page with all channels.

# Deploy (after the subscription renewal and live-URL patches you already applied):

```bash
cd /srv/www/html
git apply --check automaid_fiuu_card_direct.patch
git apply automaid_fiuu_card_direct.patch
php artisan optimize:clear
sudo systemctl restart php8.x-fpm     # your PHP-FPM service name
```

There's no migration.

# Test with your own card

1. In the app, tap Subscribe. After a brief "Opening secure card payment" screen, it should land on the add-card form.
2. Pay. You should return to the app with the payment confirmed, and the subscription should be Active.
3. Run php artisan automaid:fiuu-test-renewal <subscription_id>. It should show "Token account: paynwash", which confirms Fiuu saved the card.

If step 3 says there's no card token, ask Fiuu to enable tokenization and "extraP in response" for merchant paynwash.

If the page shows a "403 Invalid signature" error instead of the card form, tell me. Your server's APP_URL may not be https://app.automaid.asia.


Automaid fiuu card direct
PATCH 

