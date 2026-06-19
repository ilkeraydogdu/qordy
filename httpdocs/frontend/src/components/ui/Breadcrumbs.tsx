import { Link } from "react-router-dom";
import { Icon } from "@/components/ui/Icon";

export function Breadcrumbs({ items }: { items: { label: string; href?: string }[] }) {
  return (
    <nav aria-label="Breadcrumb" className="container-q pt-28 pb-4">
      <ol className="flex flex-wrap items-center gap-2 text-sm text-ink-500">
        {items.map((item, i) => {
          const isLast = i === items.length - 1;
          return (
            <li key={item.label} className="flex items-center gap-2">
              {i > 0 && <Icon name="chevronRight" size={14} className="text-ink-400" />}
              {isLast || !item.href ? (
                <span className={isLast ? "text-ink-800 font-medium" : undefined}>{item.label}</span>
              ) : (
                <Link to={item.href} className="hover:text-brand-600 transition-colors">
                  {item.label}
                </Link>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
