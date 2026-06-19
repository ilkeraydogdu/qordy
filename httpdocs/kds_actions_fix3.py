import re

css_path = "/var/www/vhosts/qordy.com/httpdocs/public/assets/css/business-theme.css"
with open(css_path, "r", encoding="utf-8") as f:
 css = f.read()

anchor = "#kitchen-dashboard .kds-page-header h1.kds-title {\n white-space: nowrap;\n}\n"
if anchor in css:
 insertion = (
 "\n"
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
 css = css.replace(anchor, anchor + insertion, 1)
 print("Added kds-actions CSS")
 with open(css_path, "w", encoding="utf-8") as f:
 f.write(css)
else:
 print("Anchor not found")
