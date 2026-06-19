import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import path from "node:path";
// Build output goes straight into the PHP project's public directory
// so Apache can serve the assets at https://qordy.com/app/…
export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            "@": path.resolve(__dirname, "./src"),
        },
    },
    base: "/app/",
    build: {
        outDir: path.resolve(__dirname, "../public/app"),
        emptyOutDir: true,
        assetsDir: "assets",
        sourcemap: false,
        rollupOptions: {
            output: {
                manualChunks: {
                    gsap: ["gsap", "@gsap/react"],
                    motion: ["framer-motion"],
                    lenis: ["lenis"],
                },
            },
        },
    },
    server: {
        port: 5173,
        // Not: dev'de /assets public klasöründen serve edilir (Vite default).
        // Production'da PHP tarafı public/app/asset'lerini kendi sunar.
        // Eski proxy'ler (https://qordy.com) geliştirmeyi yavaşlatıp 500'e düşürüyordu.
        // Gerekirse ileride tek tek ekle: proxy: { "/api": "..." }
    },
});
