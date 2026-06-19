import re

path = "/var/www/vhosts/qordy.com/httpdocs/app/views/kitchen/dashboard.php"
css_path = "/var/www/vhosts/qordy.com/httpdocs/public/assets/css/business-theme.css"

with open(path, "r", encoding="utf-8") as f:
 content = f.read()

old = '<div class="flex flex-wrap items-center gap-2 sm:gap-3 shrink-0">'
new = '<div class="kds-actions flex flex-wrap items-center gap-2 sm:gap-3">'
if old in content:
 content = content.replace(old, new, 1)
 print("Replaced actions wrapper")
else:
 print("Actions wrapper not found")

with open(path, "w", encoding="utf-8") as f:
 f.write(content)

with open(css_path, "r", encoding="utf-8") as f:
 css = f.read()

anchor_pattern = re.compile(
 r'#kitchen-dashboard \.kds-page-header h1\.kds-title \{[^}]*?\}',
 re.DOTALL
)
m = anchor_pattern.search(css)
if m:
 insertion = (
 "\n\n"
 "#kitchen-dashboard .kds-actions {\n"
 " flex-shrink: 0;\n"
 " justify-content: flex-start;\n"
 "}\n"
 "\n"
 "@media (min-width: 1024px) {\n"
 " #kitchen-dashboard .kds-actions {\n"
 " justify-content: flex-end;\n"
 " }\n"
 "}\n"
 "\n"
 "@media (max-width: 639px) {\n"
 " #kitchen-dashboard .kds-actions {\n"
 " width: 100%;\n"
 " justify-content: space-between;\n"
 " }\n"
 "}\n"
 )
 css = css.replace(m.group(1), m.group(1) + insertion, 1)
 print("Added kds-actions CSS")
else:
 print("CSS anchor not found")

with open(css_path, "w", encoding="utf-8") as f:
 f.write(css)
print("Done")
