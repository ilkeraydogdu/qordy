import { useEffect } from "react";
import { useLocation } from "react-router-dom";
import { breadcrumbJsonLd, getSeoForPath } from "@/lib/seo";

function upsertMeta(name: string, content: string, attr: "name" | "property" = "name") {
  let el = document.querySelector<HTMLMetaElement>(`meta[${attr}="${name}"]`);
  if (!el) {
    el = document.createElement("meta");
    el.setAttribute(attr, name);
    document.head.appendChild(el);
  }
  el.content = content;
}

function upsertLink(rel: string, href: string) {
  let el = document.querySelector<HTMLLinkElement>(`link[rel="${rel}"]`);
  if (!el) {
    el = document.createElement("link");
    el.rel = rel;
    document.head.appendChild(el);
  }
  el.href = href;
}

function upsertJsonLd(id: string, data: Record<string, unknown>) {
  let el = document.getElementById(id) as HTMLScriptElement | null;
  if (!el) {
    el = document.createElement("script");
    el.id = id;
    el.type = "application/ld+json";
    document.head.appendChild(el);
  }
  el.textContent = JSON.stringify(data);
}

export function PageMeta({
  title,
  description,
  path,
  robots,
  breadcrumbs,
}: {
  title?: string;
  description?: string;
  path?: string;
  robots?: string;
  breadcrumbs?: { name: string; path: string }[];
}) {
  const { pathname } = useLocation();
  const defaults = getSeoForPath(pathname);
  const finalTitle = title ?? defaults.title;
  const finalDesc = description ?? defaults.description;
  const canonicalPath = path ?? defaults.path;
  const canonical = `https://qordy.com${canonicalPath === "/" ? "" : canonicalPath}`;

  useEffect(() => {
    document.title = finalTitle;
    upsertMeta("description", finalDesc);
    upsertMeta("robots", robots ?? defaults.robots ?? "index, follow, max-image-preview:large");
    upsertLink("canonical", canonical);
    upsertMeta("og:title", finalTitle, "property");
    upsertMeta("og:description", finalDesc, "property");
    upsertMeta("og:url", canonical, "property");
    upsertMeta("og:type", "website", "property");
    upsertMeta("og:locale", "tr_TR", "property");
    upsertMeta("og:site_name", "QORDY", "property");
    upsertMeta("twitter:card", "summary_large_image");
    upsertMeta("twitter:title", finalTitle);
    upsertMeta("twitter:description", finalDesc);

    if (breadcrumbs && breadcrumbs.length > 0) {
      upsertJsonLd("qordy-breadcrumb-ld", breadcrumbJsonLd(breadcrumbs));
    }
  }, [finalTitle, finalDesc, canonical, robots, defaults.robots, breadcrumbs]);

  return null;
}
