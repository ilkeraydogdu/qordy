import re

path = "/var/www/vhosts/qordy.com/httpdocs/public/assets/css/business-theme.css"
with open(path, "r", encoding="utf-8") as f:
 content = f.read()

pattern1 = re.compile(
 r'#kitchen-dashboard\.q-biz-ops \{\s*display: flex;\s*flex-direction: column;\s*min-height: 0;\s*\}'
)
m1 = pattern1.search(content)
if not m1:
 print("Pattern 1 not found")
 raise SystemExit(1)

indent = " "
replacement1 = (
 "#kitchen-dashboard.q-biz-ops {\n"
 + indent + "display: flex;\n"
 + indent + "flex-direction: column;\n"
 + indent + "min-height: 0;\n"
 + indent + "width: 100%;\n"
 + indent + "max-width: 100%;\n"
 + indent + "box-sizing: border-box;\n"
 + "}"
)
content = content.replace(m1.group(0), replacement1, 1)
print("Replaced q-biz-ops block")

pattern2 = re.compile(
 r'#kitchen-dashboard \.kds-page-header \{\s*flex-shrink: 0;\s*\}'
)
m2 = pattern2.search(content)
if m2:
 replacement2 = (
 "#kitchen-dashboard .kds-page-header {\n"
 + indent + "flex-shrink: 0;\n"
 + indent + "padding: 0.5rem 0 1.5rem;\n"
 + indent + "border-bottom: 1px dashed #e2e8f0;\n"
 + indent + "margin-bottom: 1.5rem;\n"
 + "}\n\n"
 + "#kitchen-dashboard .kds-page-header > div:first-child {\n"
 + indent + "align-items: flex-start;\n"
 + indent + "gap: 1rem;\n"
 + "}\n\n"
 + "#kitchen-dashboard .kds-page-header h1.kds-title {\n"
 + indent + "white-space: nowrap;\n"
 + "}"
 )
 content = content.replace(m2.group(0), replacement2, 1)
 print("Enhanced kds-page-header")
else:
 print("kds-page-header pattern not matched")

with open(path, "w", encoding="utf-8") as f:
 f.write(content)
print("Done")
