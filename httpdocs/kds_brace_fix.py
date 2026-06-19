path = "/var/www/vhosts/qordy.com/httpdocs/public/assets/css/business-theme.css"
fh = open(path, "r", encoding="utf-8")
css = fh.read()
fh.close()

bad = "}\n}\n\n#kitchen-dashboard .kds-order-card {"
good = "}\n\n#kitchen-dashboard .kds-order-card {"
if bad in css:
 css = css.replace(bad, good, 1)
 print("Fixed extra brace")
 fh = open(path, "w", encoding="utf-8")
 fh.write(css)
 fh.close()
else:
 print("Pattern not found")
