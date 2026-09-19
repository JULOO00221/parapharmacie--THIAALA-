import type { Metadata } from "next";
import { Fraunces, Work_Sans } from "next/font/google";
import { CartDrawer } from "@/components/cart/CartDrawer";
import { CartProvider } from "@/components/cart/CartProvider";
import { AnnouncementBar } from "@/components/layout/AnnouncementBar";
import { Footer } from "@/components/layout/Footer";
import { Header } from "@/components/layout/Header";
import "./globals.css";

/*
 * Polices auto-hébergées par next/font (DESIGN.md : jamais de <link> vers
 * Google Fonts, qui bloque le rendu sur les connexions faibles). Les deux
 * sont des polices variables : un seul fichier couvre tous les poids.
 */
const workSans = Work_Sans({
  variable: "--font-work-sans",
  subsets: ["latin"],
  display: "swap",
});

/** Titres uniquement — voir l'utilitaire `font-titre` dans globals.css. */
const fraunces = Fraunces({
  variable: "--font-fraunces",
  subsets: ["latin"],
  display: "swap",
});

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000";

export const metadata: Metadata = {
  metadataBase: new URL(siteUrl),
  title: {
    default: "Parapharmacie THIAALA à Tambacounda — Santé • Beauté • Bien-être",
    template: "%s — Parapharmacie THIAALA",
  },
  description:
    "Parapharmacie THIAALA à Tambacounda : soins du visage, du corps, cheveux, bébé et hygiène. Livraison à Tambacounda et dans la région, paiement à la livraison ou par Wave.",
  openGraph: {
    siteName: "Parapharmacie THIAALA",
    locale: "fr_SN",
    type: "website",
  },
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html
      lang="fr"
      className={`${workSans.variable} ${fraunces.variable} h-full antialiased`}
    >
      <body className="flex min-h-full flex-col bg-ivoire font-sans text-encre">
        <CartProvider>
          <AnnouncementBar />
          <Header />
          <div className="flex-1">{children}</div>
          <Footer />
          <CartDrawer />
        </CartProvider>
      </body>
    </html>
  );
}
