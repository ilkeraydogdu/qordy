path = "/var/www/vhosts/qordy.com/httpdocs/app/views/kitchen/dashboard.php"
fh = open(path, "r", encoding="utf-8")
content = fh.read()
fh.close()

old = ' <header class="kds-page-header flex flex-col gap-4 sm:gap-5">\n <div class="flex flex-col lg:flex-row justify-between lg:items-start gap-4 lg:gap-6">'
new = ' <header class="kds-page-header flex flex-col gap-4 sm:gap-5">\n <div class="flex flex-col lg:flex-row justify-between lg:items-start gap-4 lg:gap-6">'
if old in content:
 content = content.replace(old, new, 1)
 print("Fixed indent")
 fh = open(path, "w", encoding="utf-8")
 fh.write(content)
 fh.close()
else:
 print("Pattern not found")
