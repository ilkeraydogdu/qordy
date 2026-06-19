import re

path = "/var/www/vhosts/qordy.com/httpdocs/app/views/kitchen/dashboard.php"
with open(path, "r", encoding="utf-8") as f:
 content = f.read()

# Use regex to find and replace the kitchen-dashboard opening div
m = re.search(r'<div id="kitchen-dashboard"[^>]*>', content)
if m:
 new1 = '<div id="kitchen-dashboard" class="p-4 sm:p-5 md:p-6 lg:p-7 xl:p-8 h-full overflow-y-auto animate-slide-up q-biz-theme q-biz-ops q-biz-ops-scroll" style="<?php echo $isSuperAdmin ? \'display: none;\' : \'\'; ?>">'
 content = content.replace(m.group(0), new1, 1)
 print("Replaced container div")
else:
 print("Container div not found")
 raise SystemExit(1)

# Header block
m = re.search(r'<header class="kds-page-header[^"]*">\s*<div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3 sm:gap-4">', content)
if m:
 old_block = m.group(0)
 new_block = '<header class="kds-page-header flex flex-col gap-4 sm:gap-5">\n <div class="flex flex-col lg:flex-row justify-between lg:items-start gap-4 lg:gap-6">'
 content = content.replace(old_block, new_block, 1)
 print("Replaced header layout")
else:
 print("Header pattern not found")

# Toolbar
m = re.search(r'<div class="flex flex-col sm:flex-row gap-2 sm:gap-3">', content)
if m:
 content = content.replace(m.group(0), '<div class="kds-toolbar gap-3">', 1)
 print("Replaced toolbar")
else:
 print("Toolbar not found")

with open(path, "w", encoding="utf-8") as f:
 f.write(content)
print("Done")
