import type { Metadata } from "next";
import { JetBrains_Mono, Newsreader, Plus_Jakarta_Sans } from "next/font/google";
import "./globals.css";

const editorialSerif = Newsreader({
  variable: "--font-editorial-serif",
  subsets: ["latin"],
  style: ["normal", "italic"],
  display: "swap",
});

const uiSans = Plus_Jakarta_Sans({
  variable: "--font-ui-sans",
  subsets: ["latin"],
  display: "swap",
});

const codeMono = JetBrains_Mono({
  variable: "--font-code-mono",
  subsets: ["latin"],
  display: "swap",
});

export const metadata: Metadata = {
  title: {
    default: "Herbarium — Open-Source Digital Herbarium",
    template: "%s · Herbarium",
  },
  description:
    "Open-source digital herbarium for universities, researchers, students, and botanical collectors. Catalog plant taxonomy, pressed specimens, determination history, and shareable flora portals.",
};

const themeInitScript = `
(function(){
  try {
    var stored = localStorage.getItem('herbarium-theme');
    var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (stored === 'dark' || (!stored && prefersDark)) {
      document.documentElement.classList.add('dark');
    }
  } catch (e) {}
})();
`;

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="en"
      suppressHydrationWarning
      className={`${editorialSerif.variable} ${uiSans.variable} ${codeMono.variable} h-full antialiased`}
    >
      <head>
        <script dangerouslySetInnerHTML={{ __html: themeInitScript }} />
      </head>
      <body className="min-h-full flex flex-col bg-canvas text-ink">
        {children}
      </body>
    </html>
  );
}
