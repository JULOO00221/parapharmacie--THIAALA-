import type { Metadata } from "next";
import { Fraunces, Geist, Geist_Mono } from "next/font/google";
import { CartDrawer } from "@/components/cart/CartDrawer";
import { CartProvider } from "@/components/cart/CartProvider";
import { Footer } from "@/components/layout/Footer";
import { Header } from "@/components/layout/Header";
import { ReassuranceBar } from "@/components/layout/ReassuranceBar";
import "./globals.css";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

/** Display face for H1/hero and major section headings only — see globals.css's --font-display. */
const fraunces = Fraunces({
  variable: "--font-fraunces",
  subsets: ["latin"],
  weight: ["500", "600"],
});

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000";

export const metadata: Metadata = {
  metadataBase: new URL(siteUrl),
  title: {
    default: "Tambacounda Cosmetix — Parapharmacie & cosmétiques",
    template: "%s — Tambacounda Cosmetix",
  },
  description:
    "Parapharmacie et cosmétiques au Sénégal : soins du visage, du corps, cheveux et hygiène, sélectionnés avec soin.",
  openGraph: {
    siteName: "Tambacounda Cosmetix",
    locale: "fr_SN",
    type: "website",
  },
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html
      lang="fr"
      className={`${geistSans.variable} ${geistMono.variable} ${fraunces.variable} h-full antialiased`}
    >
      <body className="flex min-h-full flex-col bg-surface text-ink">
        <CartProvider>
          <Header />
          <ReassuranceBar />
          <div className="flex-1">{children}</div>
          <Footer />
          <CartDrawer />
        </CartProvider>
      </body>
    </html>
  );
}
