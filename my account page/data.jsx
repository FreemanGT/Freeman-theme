// Mock data for FOLIO account
const ORDERS = [
  {
    id: "FL-2491-08",
    date: "Apr 18, 2026",
    status: "In transit",
    total: 412.00,
    items: [
      { name: "Heavy Cotton Crewneck", variant: "Bone / M", qty: 1, price: 145.00, sku: "HCC-BNE-M" },
      { name: "Pleated Wool Trouser", variant: "Charcoal / 32", qty: 1, price: 248.00, sku: "PWT-CHR-32" },
    ],
    shipping: { carrier: "DHL Express", eta: "Apr 30", track: "JD0142893221" },
    address: "12 Rue de Turenne, 75003 Paris, FR",
    timeline: [
      { label: "Order placed", date: "Apr 18", done: true },
      { label: "Confirmed", date: "Apr 18", done: true },
      { label: "Shipped", date: "Apr 22", done: true },
      { label: "Out for delivery", date: "Apr 30", done: false },
      { label: "Delivered", date: "—", done: false },
    ],
  },
  {
    id: "FL-2418-22",
    date: "Mar 02, 2026",
    status: "Delivered",
    total: 198.00,
    items: [
      { name: "Linen Camp Shirt", variant: "Sand / L", qty: 1, price: 168.00, sku: "LCS-SND-L" },
      { name: "Cotton Tube Sock (3-pk)", variant: "Bone", qty: 1, price: 30.00, sku: "CTS-BNE" },
    ],
    shipping: { carrier: "UPS", eta: "—", track: "1Z999AA1" },
    address: "12 Rue de Turenne, 75003 Paris, FR",
    timeline: [
      { label: "Order placed", date: "Mar 02", done: true },
      { label: "Confirmed", date: "Mar 02", done: true },
      { label: "Shipped", date: "Mar 04", done: true },
      { label: "Delivered", date: "Mar 08", done: true },
    ],
  },
  {
    id: "FL-2376-04",
    date: "Jan 14, 2026",
    status: "Delivered",
    total: 624.00,
    items: [
      { name: "Felted Wool Overcoat", variant: "Walnut / M", qty: 1, price: 624.00, sku: "FWO-WAL-M" },
    ],
    shipping: { carrier: "DHL Express", eta: "—", track: "JD0142811112" },
    address: "12 Rue de Turenne, 75003 Paris, FR",
    timeline: [
      { label: "Order placed", date: "Jan 14", done: true },
      { label: "Delivered", date: "Jan 21", done: true },
    ],
  },
  {
    id: "FL-2301-19",
    date: "Nov 28, 2025",
    status: "Delivered",
    total: 89.00,
    items: [
      { name: "Recycled Cashmere Beanie", variant: "Moss / OS", qty: 1, price: 89.00, sku: "RCB-MOS" },
    ],
    shipping: { carrier: "USPS", eta: "—", track: "9400-110" },
    address: "12 Rue de Turenne, 75003 Paris, FR",
    timeline: [
      { label: "Order placed", date: "Nov 28", done: true },
      { label: "Delivered", date: "Dec 03", done: true },
    ],
  },
  {
    id: "FL-2289-77",
    date: "Oct 09, 2025",
    status: "Returned",
    total: 0.00,
    items: [
      { name: "Garment-dyed Tee", variant: "Olive / S", qty: 2, price: 58.00, sku: "GDT-OLV-S" },
    ],
    shipping: { carrier: "—", eta: "—", track: "—" },
    address: "12 Rue de Turenne, 75003 Paris, FR",
    timeline: [
      { label: "Order placed", date: "Oct 09", done: true },
      { label: "Returned", date: "Oct 25", done: true },
    ],
  },
];

const ADDRESSES = [
  { id: "a1", label: "Home", default: true, name: "Margaux Béchard", lines: ["12 Rue de Turenne", "Apt 4B", "75003 Paris", "France"], phone: "+33 6 12 34 56 78" },
  { id: "a2", label: "Studio", default: false, name: "Margaux Béchard / Atelier", lines: ["8 Passage du Caire", "75002 Paris", "France"], phone: "+33 1 42 33 09 12" },
];

const PAYMENTS = [
  { id: "p1", brand: "Visa", last4: "4012", exp: "08/27", default: true, holder: "M. Béchard" },
  { id: "p2", brand: "Mastercard", last4: "8821", exp: "11/26", default: false, holder: "M. Béchard" },
];

const DOWNLOADS = [
  { id: "d1", name: "Care Guide — Wool & Cashmere", file: "folio-care-wool.pdf", remaining: "Unlimited", expires: "Never", order: "FL-2376-04" },
  { id: "d2", name: "FOLIO Lookbook — SS26", file: "ss26-lookbook.pdf", remaining: "3 of 5", expires: "Jul 01, 2026", order: "FL-2491-08" },
  { id: "d3", name: "Tailoring Measurement Sheet", file: "measurement-sheet.pdf", remaining: "Unlimited", expires: "Never", order: "—" },
];

const WISHLIST = [
  { id: "w1", name: "Boiled Wool Cardigan", color: "Ecru", price: 285.00, status: "In stock" },
  { id: "w2", name: "Pleated Midi Skirt", color: "Ink", price: 220.00, status: "Low stock" },
  { id: "w3", name: "Suede Derby", color: "Cocoa", price: 395.00, status: "Restocking" },
  { id: "w4", name: "Silk Crepe Blouse", color: "Bone", price: 248.00, status: "In stock" },
];

const SUBSCRIPTIONS = [
  { id: "s1", name: "Essentials Box", cadence: "Quarterly", next: "Jun 15, 2026", price: 120.00, status: "Active" },
  { id: "s2", name: "Care & Repair Service", cadence: "Annual", next: "Jan 02, 2027", price: 60.00, status: "Active" },
];

const ACTIVITY = [
  { date: "Apr 22", text: "Order FL-2491-08 shipped via DHL Express", kind: "ship" },
  { date: "Apr 18", text: "Order FL-2491-08 confirmed — €412.00", kind: "order" },
  { date: "Apr 12", text: "Saved Boiled Wool Cardigan to wishlist", kind: "wishlist" },
  { date: "Apr 03", text: "Earned 240 points from order FL-2418-22", kind: "points" },
  { date: "Mar 28", text: "Updated default shipping address", kind: "settings" },
];

const PROFILE = {
  firstName: "Margaux",
  lastName: "Béchard",
  email: "margaux.bechard@studio-folio.fr",
  phone: "+33 6 12 34 56 78",
  birthday: "1992-07-14",
  pronouns: "she/her",
  newsletters: { drops: true, lookbooks: true, sales: false },
  size: { top: "M", bottom: "30", shoe: "EU 39" },
  joined: "Sep 2023",
};

const LOYALTY = {
  tier: "Atelier",
  nextTier: "Couture",
  points: 1840,
  toNext: 660,
  spent: 2480,
  perks: [
    { label: "Free express shipping", on: true },
    { label: "Early access to drops (24h)", on: true },
    { label: "Complimentary tailoring", on: true },
    { label: "Private trunk shows", on: false, hint: "Couture tier" },
    { label: "Annual gift", on: false, hint: "Couture tier" },
  ],
};

window.FOLIO = { ORDERS, ADDRESSES, PAYMENTS, DOWNLOADS, WISHLIST, SUBSCRIPTIONS, ACTIVITY, PROFILE, LOYALTY };
