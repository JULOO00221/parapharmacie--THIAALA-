import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  images: {
    // The Laravel API's local "public" disk, where product images live
    // once uploaded via Filament. No product has an image yet, but
    // next/image needs this declared before it will ever render one.
    remotePatterns: [
      {
        protocol: "http",
        hostname: "127.0.0.1",
        port: "8000",
        pathname: "/storage/**",
      },
      {
        protocol: "http",
        hostname: "localhost",
        port: "8000",
        pathname: "/storage/**",
      },
    ],
  },
};

export default nextConfig;
