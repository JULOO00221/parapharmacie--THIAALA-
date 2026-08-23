import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  images: {
    // Next.js 16 blocks image optimization for hostnames resolving to a
    // private/loopback IP by default (SSRF protection). The Laravel API
    // runs on localhost in dev, so this is required for any product image
    // to render locally — see the "Local IP Restriction" breaking change
    // in the Next.js 16 upgrade guide. Only appropriate for private
    // networks; production will serve images from a real public host.
    dangerouslyAllowLocalIP: true,
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
