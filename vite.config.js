import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";
import path from "path";

export default defineConfig(() => ({
  base: "",
  server: {
    host: "localhost",
    cors: true,
  },
  build: {
    emptyOutDir: true,
    manifest: true,
    outDir: "build",
    assetsDir: "assets",
  },
  plugins: [
    tailwindcss(),
    laravel({
      publicDirectory: "build",
      input: ["src/js/main.js"],
      refresh: ["**.php", "views/**"],
    }),
  ],
  resolve: {
    alias: [
      {
        find: /~(.+)/,
        replacement: process.cwd() + "/node_modules/$1",
      },
      {
        find: "@",
        replacement: path.resolve(__dirname, "./src"),
      },
      {
        find: "@styles",
        replacement: path.resolve(__dirname, "./src/css"),
      },
      {
        find: "@images",
        replacement: path.resolve(__dirname, "./src/images"),
      },
    ],
  },
}));
