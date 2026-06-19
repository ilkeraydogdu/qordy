import re

path = "/var/www/vhosts/qordy.com/httpdocs/app/views/kitchen/dashboard.php"
with open(path, "r", encoding="utf-8") as f:
 content = f.read()

# 1) Toolbar — proper full-width container, search expands
old = '<div class="kds-toolbar gap-3">'
new = '<div class="kds-toolbar gap-3 w-full">'
if old in content:
 content = content.replace(old, new, 1)
 print("Made toolbar full-width")

# 2) Select — use a sensible responsive min-width
old2 = 'class="kds-select form-input-responsive filter-button-responsive w-full sm:w-auto sm:min-w-[11rem]"'
new2 = 'class="kds-select w-full sm:w-auto sm:min-w-[12rem] shrink-0"'
if old2 in content:
 content = content.replace(old2, new2, 1)
 print("Cleaned select classes")

with open(path, "w", encoding="utf-8") as f:
 f.write(content)
