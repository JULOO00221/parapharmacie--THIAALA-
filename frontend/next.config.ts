import type { NextConfig } from "next";

/**
 * Derives an additional next/image remotePattern from NEXT_PUBLIC_API_URL
 * so a production API domain never has to be hardcoded here — it works
 * automatically the day NEXT_PUBLIC_API_URL is set to a real production
 * URL, with zero next.config.ts edit required. Returns null when the
 * variable is unset/unparsable (e.g. at a build step that doesn't need
 * it) rather than throwing — this file must never crash a build over a
 * missing image host.
 *
 * Only covers the current architecture (product images served directly by
 * Laravel's own `public` disk, same host as the API). If/when
 * FILESYSTEM_PRODUCT_DISK switches to Supabase Storage, that bucket lives
 * on a DIFFERENT host than the API itself — a separate remotePattern will
 * need to be added at that point; nothing here can infer it in advance.
 */
function apiHostRemotePattern() {
  const apiUrl = process.env.NEXT_PUBLIC_API_URL;
  if (!apiUrl) return null;

  try {
    const { protocol, hostname, port } = new URL(apiUrl);
    if (protocol !== "http:" && protocol !== "https:") return null;

    return {
      protocol: protocol.replace(":", "") as "http" | "https",
      hostname,
      port,
      pathname: "/storage/**" as const,
    };
  } catch {
    return null;
  }
}

const derivedApiPattern = apiHostRemotePattern();

const nextConfig: NextConfig = {
  images: {
    // Next.js 16 blocks image optimization for hostnames resolving to a
    // private/loopback IP by default (SSRF protection). Only needed for
    // local development, where the Laravel API runs on a private IP —
    // never enabled in production, where the API is a real public host.
    dangerouslyAllowLocalIP: process.env.NODE_ENV !== "production",
    remotePatterns: [
      // Static dev-only patterns — kept explicit so `next dev` never
      // depends on NEXT_PUBLIC_API_URL being set to work.
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
      // Production (or any non-default) API host, derived automatically —
      // see apiHostRemotePattern() above. Absent/duplicate in dev is
      // harmless.
      ...(derivedApiPattern ? [derivedApiPattern] : []),
    ],
  },
};

export default nextConfig;
