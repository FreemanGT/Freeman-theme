// App shell — routing, animations, tweaks
const { useState, useEffect } = React;

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "radius": 16,
  "monochrome": true,
  "density": "regular",
  "italics": true
}/*EDITMODE-END*/;

const App = () => {
  const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);
  const [active, setActive] = useState("dashboard");
  const [openOrder, setOpenOrder] = useState(null);
  const [toastMsg, setToastMsg] = useState("");
  const [animKey, setAnimKey] = useState(0);

  // Apply tweaks to CSS vars
  useEffect(() => {
    const r = document.documentElement.style;
    r.setProperty("--radius", `${t.radius}px`);
    r.setProperty("--radius-sm", `${Math.max(2, t.radius * 0.625)}px`);
    if (t.monochrome) {
      r.setProperty("--accent", "#0a0a0a");
      r.setProperty("--accent-soft", "#f0f0f0");
      r.setProperty("--positive", "#0a0a0a");
      r.setProperty("--warn", "#555555");
    } else {
      r.setProperty("--accent", "oklch(0.62 0.14 45)");
      r.setProperty("--accent-soft", "oklch(0.94 0.04 45)");
      r.setProperty("--positive", "oklch(0.55 0.12 155)");
      r.setProperty("--warn", "oklch(0.62 0.14 75)");
    }
    // Density: scale page padding
    const pad = t.density === "compact" ? 36 : t.density === "comfy" ? 72 : 56;
    r.setProperty("--page-pad", `${pad}px`);
    // Italics toggle: add/remove class on body
    document.body.classList.toggle("no-italics", !t.italics);
  }, [t.radius, t.monochrome, t.density, t.italics]);

  const toast = (m) => setToastMsg(m);

  const nav = (id) => {
    setOpenOrder(null);
    setActive(id);
    setAnimKey(k => k + 1);
  };

  const openOrderDetail = (id) => {
    setOpenOrder(id);
    setActive("orders");
    setAnimKey(k => k + 1);
  };

  const back = () => { setOpenOrder(null); setAnimKey(k => k + 1); };

  const crumbs = (() => {
    const navItem = NAV.find(n => n.id === active);
    if (active === "logout") return ["Account", "Sign out"];
    if (openOrder) return ["Account", "Orders", openOrder];
    return ["Account", navItem ? navItem.label : "Dashboard"];
  })();

  let content;
  if (active === "logout") content = <LogoutSection onCancel={() => nav("dashboard")} />;
  else if (openOrder) content = <OrderDetailSection orderId={openOrder} onBack={back} toast={toast} />;
  else if (active === "dashboard") content = <DashboardSection onNav={nav} onOpenOrder={openOrderDetail} toast={toast} />;
  else if (active === "orders") content = <OrdersSection onOpenOrder={openOrderDetail} />;
  else if (active === "downloads") content = <DownloadsSection toast={toast} />;
  else if (active === "wishlist") content = <WishlistSection toast={toast} />;
  else if (active === "subscriptions") content = <SubscriptionsSection toast={toast} />;
  else if (active === "loyalty") content = <LoyaltySection />;
  else if (active === "addresses") content = <AddressesSection toast={toast} />;
  else if (active === "payments") content = <PaymentsSection toast={toast} />;
  else if (active === "account") content = <AccountSection toast={toast} />;

  return (
    <div style={{ display: "flex", minHeight: "100vh", background: "var(--bg)" }}>
      <Sidebar active={openOrder ? "orders" : active} onNav={nav} />
      <main style={{ flex: 1, minWidth: 0 }}>
        <Topbar crumbs={crumbs} />
        <div style={{ maxWidth: 1180, margin: "0 auto", padding: "var(--page-pad, 56px) var(--page-pad, 56px) 80px" }}>
          <div key={animKey} style={{ animation: "sectionIn 360ms cubic-bezier(0.2, 0.8, 0.2, 1)" }}>
            {content}
          </div>
        </div>
      </main>
      <Toast msg={toastMsg} onClose={() => setToastMsg("")} />

      <TweaksPanel>
        <TweakSection label="Shape" />
        <TweakSlider
          label="Border radius"
          value={t.radius}
          min={0} max={24} step={1} unit="px"
          onChange={(v) => setTweak("radius", v)}
        />
        <TweakSection label="Theme" />
        <TweakToggle
          label="Monochrome"
          value={t.monochrome}
          onChange={(v) => setTweak("monochrome", v)}
        />
        <TweakToggle
          label="Editorial italics"
          value={t.italics}
          onChange={(v) => setTweak("italics", v)}
        />
        <TweakSection label="Layout" />
        <TweakRadio
          label="Density"
          value={t.density}
          options={["compact", "regular", "comfy"]}
          onChange={(v) => setTweak("density", v)}
        />
      </TweaksPanel>

      <style>{`
        body.no-italics * { font-style: normal !important; }
        @keyframes sectionIn {
          from { opacity: 0; transform: translateY(8px); }
          to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideIn {
          from { transform: translateX(40px); opacity: 0; }
          to { transform: translateX(0); opacity: 1; }
        }
        @keyframes toastIn {
          from { transform: translate(-50%, 12px); opacity: 0; }
          to { transform: translate(-50%, 0); opacity: 1; }
        }
      `}</style>
    </div>
  );
};

ReactDOM.createRoot(document.getElementById("root")).render(<App />);
