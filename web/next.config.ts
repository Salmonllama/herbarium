import type { NextConfig } from "next";

const internalApiUrl = process.env.INTERNAL_API_URL || "http://127.0.0.1:8000";

const nextConfig: NextConfig = {
  output: "standalone",
  images: {
    unoptimized: true,
  },
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
  async rewrites() {
    return [
      {
        source: "/api/:path*",
        destination: `${internalApiUrl}/api/:path*`,
      },
      {
        source: "/sanctum/:path*",
        destination: `${internalApiUrl}/sanctum/:path*`,
      },
      {
        source: "/auth/:path*",
        destination: `${internalApiUrl}/auth/:path*`,
      },
    ];
  },
};

export default nextConfig;
