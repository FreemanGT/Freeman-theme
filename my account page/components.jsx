// Shared UI primitives + Sidebar + Topbar
const { useState, useEffect, useRef, useMemo } = React;

// ─── ICONS ─────────────────────────────────────────────────────────
const Icon = ({ name, size = 16, stroke = 1.4 }) => {
  const paths = {
    dashboard: <><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></>,
    box: <><path d="M3 7l9-4 9 4v10l-9 4-9-4V7z"/><path d="M3 7l9 4 9-4"/><path d="M12 11v10"/></>,
    pin: <><path d="M12 21s7-7.5 7-12a7 7 0 10-14 0c0 4.5 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></>,
    card: <><rect x="3" y="6" width="18" height="13" rx="1.5"/><path d="M3 10h18"/></>,
    user: <><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-7 8-7s8 3 8 7"/></>,
    download: <><path d="M12 4v12"/><path d="M7 11l5 5 5-5"/><path d="M4 20h16"/></>,
    heart: <><path d="M12 20s-7-4.5-7-10a4 4 0 017-2.5A4 4 0 0119 10c0 5.5-7 10-7 10z"/></>,
    repeat: <><path d="M17 3l4 4-4 4"/><path d="M21 7H7a4 4 0 00-4 4v0"/><path d="M7 21l-4-4 4-4"/><path d="M3 17h14a4 4 0 004-4v0"/></>,
    star: <><path d="M12 3l2.7 5.6 6.3.9-4.5 4.4 1 6.1L12 17l-5.5 3 1-6.1L3 9.5l6.3-.9L12 3z"/></>,
    logout: <><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></>,
    arrow: <><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></>,
    chevron: <><path d="M9 6l6 6-6 6"/></>,
    check: <><path d="M5 12l5 5 9-11"/></>,
    plus: <><path d="M12 5v14"/><path d="M5 12h14"/></>,
    edit: <><path d="M4 20h4l11-11-4-4L4 16v4z"/></>,
    trash: <><path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/></>,
    search: <><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.5-4.5"/></>,
    bell: <><path d="M6 8a6 6 0 1112 0v4l2 4H4l2-4V8z"/><path d="M10 19a2 2 0 004 0"/></>,
    close: <><path d="M6 6l12 12"/><path d="M18 6L6 18"/></>,
    truck: <><rect x="2" y="7" width="12" height="9"/><path d="M14 10h5l3 3v3h-8"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></>,
    sparkle: <><path d="M12 3v6"/><path d="M12 15v6"/><path d="M3 12h6"/><path d="M15 12h6"/><path d="M6 6l3 3"/><path d="M15 15l3 3"/><path d="M18 6l-3 3"/><path d="M9 15l-3 3"/></>,
  };
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={stroke} strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
      {paths[name]}
    </svg>
  );
};

// ─── BRAND MARK ────────────────────────────────────────────────────
const BrandMark = ({ size = 22 }) => (
  <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <rect x="2" y="2" width="20" height="20" stroke="currentColor" strokeWidth="1.6" />
      <path d="M7 7h10M7 12h7M7 17h4" stroke="currentColor" strokeWidth="1.6" strokeLinecap="square" />
    </svg>
    <span style={{ fontFamily: "var(--sans)", fontWeight: 600, letterSpacing: "0.18em", fontSize: 13 }}>FOLIO</span>
  </div>
);

// ─── SIDEBAR ───────────────────────────────────────────────────────
const NAV = [
  { id: "dashboard", label: "Dashboard", icon: "dashboard" },
  { id: "orders", label: "Orders", icon: "box" },
  { id: "downloads", label: "Downloads", icon: "download" },
  { id: "wishlist", label: "Wishlist", icon: "heart" },
  { id: "subscriptions", label: "Subscriptions", icon: "repeat" },
  { id: "loyalty", label: "Atelier rewards", icon: "star" },
  { id: "addresses", label: "Addresses", icon: "pin" },
  { id: "payments", label: "Payment methods", icon: "card" },
  { id: "account", label: "Account details", icon: "user" },
];

const Sidebar = ({ active, onNav }) => {
  return (
    <aside style={sidebarStyles.aside}>
      <div style={sidebarStyles.brand}>
        <BrandMark />
      </div>

      <div style={sidebarStyles.greetBlock}>
        <div className="mono" style={{ color: "var(--ink-3)" }}>Member since {window.FOLIO.PROFILE.joined}</div>
        <div style={{ fontFamily: "var(--serif)", fontSize: 22, lineHeight: 1.15, marginTop: 8, fontWeight: 400 }}>
          Bonjour,<br/>
          <span style={{ fontStyle: "italic" }}>Margaux.</span>
        </div>
      </div>

      <nav style={{ display: "flex", flexDirection: "column", padding: "8px 12px" }}>
        {NAV.map((n) => {
          const isActive = n.id === active;
          return (
            <button key={n.id} onClick={() => onNav(n.id)} style={{ ...sidebarStyles.navItem, ...(isActive ? sidebarStyles.navItemActive : {}) }}
              onMouseEnter={(e) => { if (!isActive) e.currentTarget.style.background = "var(--bg-alt)"; }}
              onMouseLeave={(e) => { if (!isActive) e.currentTarget.style.background = "transparent"; }}
            >
              <span style={sidebarStyles.navIcon}><Icon name={n.icon} size={15} /></span>
              <span style={{ flex: 1, textAlign: "left" }}>{n.label}</span>
              {isActive && <span style={sidebarStyles.activeBar} />}
            </button>
          );
        })}
      </nav>

      <div style={{ marginTop: "auto", padding: "16px 24px 24px", borderTop: "1px solid var(--line)" }}>
        <button onClick={() => onNav("logout")} style={sidebarStyles.logout}
          onMouseEnter={(e) => e.currentTarget.style.color = "var(--ink)"}
          onMouseLeave={(e) => e.currentTarget.style.color = "var(--ink-3)"}>
          <Icon name="logout" size={14} />
          <span>Sign out</span>
        </button>
      </div>
    </aside>
  );
};

const sidebarStyles = {
  aside: {
    width: 264, flexShrink: 0,
    borderRight: "1px solid var(--line)",
    background: "var(--bg)",
    display: "flex", flexDirection: "column",
    position: "sticky", top: 0, height: "100vh",
  },
  brand: { padding: "24px 24px 20px", borderBottom: "1px solid var(--line)" },
  greetBlock: { padding: "20px 24px 16px", borderBottom: "1px solid var(--line)" },
  navItem: {
    display: "flex", alignItems: "center", gap: 12,
    padding: "10px 12px", borderRadius: "var(--radius-sm)",
    fontSize: 13.5, color: "var(--ink-2)",
    transition: "background 180ms ease, color 180ms ease",
    position: "relative",
  },
  navItemActive: { color: "var(--ink)", fontWeight: 500, background: "var(--bg-alt)" },
  navIcon: { display: "flex", color: "var(--ink-3)" },
  activeBar: {
    position: "absolute", left: -12, top: "50%", transform: "translateY(-50%)",
    width: 2, height: 16, background: "var(--ink)",
  },
  logout: {
    display: "flex", alignItems: "center", gap: 10,
    fontSize: 12.5, color: "var(--ink-3)",
    transition: "color 180ms ease",
  },
};

// ─── TOPBAR ────────────────────────────────────────────────────────
const Topbar = ({ crumbs }) => {
  return (
    <div style={topbarStyles.bar}>
      <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
        {crumbs.map((c, i) => (
          <React.Fragment key={i}>
            {i > 0 && <span style={{ color: "var(--ink-4)" }}>/</span>}
            <span className="mono" style={{ color: i === crumbs.length - 1 ? "var(--ink)" : "var(--ink-3)" }}>
              {c}
            </span>
          </React.Fragment>
        ))}
      </div>
      <div style={{ display: "flex", alignItems: "center", gap: 4 }}>
        <button style={topbarStyles.iconBtn} title="Search">
          <Icon name="search" size={15} />
        </button>
        <button style={topbarStyles.iconBtn} title="Notifications">
          <Icon name="bell" size={15} />
          <span style={topbarStyles.dot} />
        </button>
        <div style={topbarStyles.avatar}>MB</div>
      </div>
    </div>
  );
};

const topbarStyles = {
  bar: {
    height: 60, padding: "0 40px",
    borderBottom: "1px solid var(--line)",
    display: "flex", alignItems: "center", justifyContent: "space-between",
    background: "var(--bg)",
    position: "sticky", top: 0, zIndex: 5,
    backdropFilter: "blur(8px)",
  },
  iconBtn: {
    width: 32, height: 32, borderRadius: "var(--radius-sm)",
    display: "inline-flex", alignItems: "center", justifyContent: "center",
    color: "var(--ink-2)", position: "relative",
    transition: "background 180ms ease",
  },
  dot: { position: "absolute", top: 8, right: 8, width: 5, height: 5, borderRadius: "50%", background: "var(--accent)" },
  avatar: {
    width: 32, height: 32, borderRadius: "50%",
    background: "var(--ink)", color: "var(--bg)",
    display: "inline-flex", alignItems: "center", justifyContent: "center",
    fontSize: 11, fontWeight: 500, letterSpacing: "0.04em", marginLeft: 8,
  },
};

// ─── PRIMITIVES ────────────────────────────────────────────────────
const Pill = ({ children, kind = "default" }) => {
  const map = {
    default: { bg: "var(--bg-alt)", color: "var(--ink-2)", border: "var(--line)" },
    positive: { bg: "transparent", color: "var(--positive)", border: "var(--positive)" },
    transit: { bg: "transparent", color: "var(--accent)", border: "var(--accent)" },
    muted:    { bg: "transparent", color: "var(--ink-3)", border: "var(--line)" },
    warn:     { bg: "transparent", color: "var(--warn)", border: "var(--warn)" },
  };
  const s = map[kind];
  return (
    <span className="mono" style={{
      display: "inline-flex", alignItems: "center", gap: 6,
      padding: "3px 8px", borderRadius: 999,
      border: `1px solid ${s.border}`, color: s.color, background: s.bg,
      fontSize: 10,
    }}>
      <span style={{ width: 4, height: 4, borderRadius: "50%", background: "currentColor" }} />
      {children}
    </span>
  );
};

const Btn = ({ children, kind = "primary", icon, onClick, type = "button", size = "md", style: extra = {} }) => {
  const base = {
    display: "inline-flex", alignItems: "center", justifyContent: "center", gap: 8,
    fontSize: size === "sm" ? 12 : 13, fontWeight: 500,
    padding: size === "sm" ? "7px 12px" : "10px 16px",
    borderRadius: "var(--radius-sm)", transition: "all 180ms ease", cursor: "pointer",
    letterSpacing: "0.01em",
  };
  const kinds = {
    primary: { background: "var(--ink)", color: "var(--bg)", border: "1px solid var(--ink)" },
    ghost:   { background: "transparent", color: "var(--ink)", border: "1px solid var(--line)" },
    bare:    { background: "transparent", color: "var(--ink-2)", border: "1px solid transparent", padding: "6px 0" },
    accent:  { background: "var(--accent)", color: "white", border: "1px solid var(--accent)" },
  };
  const [hover, setHover] = useState(false);
  const hovered = hover ? {
    primary: { background: "var(--ink-2)" },
    ghost:   { background: "var(--bg-alt)" },
    bare:    { color: "var(--ink)" },
    accent:  { background: "oklch(0.55 0.14 45)" },
  }[kind] : {};
  return (
    <button type={type} onClick={onClick}
      onMouseEnter={() => setHover(true)} onMouseLeave={() => setHover(false)}
      style={{ ...base, ...kinds[kind], ...hovered, ...extra }}>
      {icon && <Icon name={icon} size={size === "sm" ? 12 : 14} />}
      {children}
    </button>
  );
};

const SectionHeader = ({ eyebrow, title, sub, actions }) => (
  <header style={{ display: "flex", alignItems: "flex-end", justifyContent: "space-between", marginBottom: 32, gap: 24 }}>
    <div>
      {eyebrow && <div className="mono" style={{ color: "var(--ink-3)", marginBottom: 12 }}>{eyebrow}</div>}
      <h1 style={{ fontFamily: "var(--serif)", fontWeight: 300, fontSize: 44, letterSpacing: "-0.02em", lineHeight: 1.05 }}>
        {title}
      </h1>
      {sub && <p style={{ color: "var(--ink-3)", marginTop: 12, fontSize: 14, maxWidth: 560 }}>{sub}</p>}
    </div>
    {actions && <div style={{ display: "flex", gap: 8 }}>{actions}</div>}
  </header>
);

const Field = ({ label, children, hint }) => (
  <label style={{ display: "block" }}>
    <div className="mono" style={{ color: "var(--ink-3)", marginBottom: 8 }}>{label}</div>
    {children}
    {hint && <div style={{ fontSize: 11.5, color: "var(--ink-3)", marginTop: 6 }}>{hint}</div>}
  </label>
);

const TextInput = ({ value, onChange, type = "text", placeholder, ...rest }) => {
  const [focus, setFocus] = useState(false);
  return (
    <input
      value={value} onChange={onChange} type={type} placeholder={placeholder}
      onFocus={() => setFocus(true)} onBlur={() => setFocus(false)}
      style={{
        width: "100%", padding: "11px 14px",
        background: "var(--bg)",
        border: `1px solid ${focus ? "var(--ink)" : "var(--line)"}`,
        borderRadius: "var(--radius-sm)", fontSize: 13.5,
        outline: "none", transition: "border-color 180ms ease",
      }}
      {...rest}
    />
  );
};

// Asset placeholder — striped, monospace label
const ProductPlaceholder = ({ label, w = "100%", h = 120, ratio }) => {
  const style = ratio
    ? { width: w, aspectRatio: ratio }
    : { width: w, height: h };
  return (
    <div style={{
      ...style,
      background: `repeating-linear-gradient(135deg, var(--bg-alt), var(--bg-alt) 6px, var(--bg) 6px, var(--bg) 12px)`,
      border: "1px solid var(--line)",
      display: "flex", alignItems: "center", justifyContent: "center",
      color: "var(--ink-3)", overflow: "hidden",
    }}>
      <span className="mono" style={{ fontSize: 9 }}>{label}</span>
    </div>
  );
};

const Toast = ({ msg, onClose }) => {
  useEffect(() => {
    if (!msg) return;
    const t = setTimeout(onClose, 2400);
    return () => clearTimeout(t);
  }, [msg]);
  if (!msg) return null;
  return (
    <div style={{
      position: "fixed", bottom: 24, left: "50%", transform: "translateX(-50%)",
      background: "var(--ink)", color: "var(--bg)",
      padding: "10px 18px", borderRadius: "var(--radius-sm)", fontSize: 12.5,
      display: "flex", alignItems: "center", gap: 10,
      animation: "toastIn 220ms ease",
      zIndex: 100,
    }}>
      <Icon name="check" size={14} />
      {msg}
    </div>
  );
};

Object.assign(window, { Icon, BrandMark, Sidebar, Topbar, Pill, Btn, SectionHeader, Field, TextInput, ProductPlaceholder, Toast, NAV });
