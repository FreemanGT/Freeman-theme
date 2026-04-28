// All section views
const { ORDERS, ADDRESSES, PAYMENTS, DOWNLOADS, WISHLIST, SUBSCRIPTIONS, ACTIVITY, PROFILE, LOYALTY } = window.FOLIO;

// ─── DASHBOARD ─────────────────────────────────────────────────────
const DashboardSection = ({ onNav, onOpenOrder, toast }) => {
  const lastOrder = ORDERS[0];
  return (
    <div>
      <SectionHeader
        eyebrow="Dashboard / Overview"
        title={<><span style={{ fontStyle: "italic" }}>Welcome back,</span> Margaux.</>}
        sub="Here's where things stand — your latest order, what's saved, and a few things that might need your eye."
      />

      {/* HERO ROW: 3 stat slots */}
      <div style={{ display: "grid", gridTemplateColumns: "1.4fr 1fr 1fr", gap: 0, border: "1px solid var(--line)", marginBottom: 40 }}>
        {/* Stat 1: Active order */}
        <button onClick={() => onOpenOrder(lastOrder.id)} style={{ ...statStyles.cell, textAlign: "left", cursor: "pointer", background: "var(--bg-alt)" }}>
          <div className="mono" style={{ color: "var(--ink-3)" }}>Active order</div>
          <div style={{ fontFamily: "var(--serif)", fontSize: 38, fontWeight: 300, marginTop: 14, letterSpacing: "-0.02em" }}>
            {lastOrder.id}
          </div>
          <div style={{ display: "flex", alignItems: "center", gap: 12, marginTop: 16 }}>
            <Pill kind="transit"><Icon name="truck" size={10} /> {lastOrder.status}</Pill>
            <span style={{ fontSize: 12, color: "var(--ink-3)" }}>ETA {lastOrder.shipping.eta}</span>
          </div>
          <div style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 12, color: "var(--ink-2)", marginTop: 24 }}>
            View details <Icon name="arrow" size={12} />
          </div>
        </button>

        {/* Stat 2: Loyalty */}
        <button onClick={() => onNav("loyalty")} style={{ ...statStyles.cell, borderLeft: "1px solid var(--line)", textAlign: "left", cursor: "pointer" }}>
          <div className="mono" style={{ color: "var(--ink-3)" }}>Atelier rewards</div>
          <div style={{ fontFamily: "var(--serif)", fontSize: 38, fontWeight: 300, marginTop: 14, letterSpacing: "-0.02em" }} className="num">
            {LOYALTY.points.toLocaleString()}
          </div>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginTop: 6 }}>
            points · <span style={{ color: "var(--ink-2)" }}>{LOYALTY.tier} tier</span>
          </div>
          {/* progress */}
          <div style={{ marginTop: 18 }}>
            <div style={{ height: 2, background: "var(--line)", position: "relative" }}>
              <div style={{
                position: "absolute", left: 0, top: 0, height: "100%",
                width: `${(LOYALTY.points / (LOYALTY.points + LOYALTY.toNext)) * 100}%`,
                background: "var(--ink)", transition: "width 600ms ease",
              }} />
            </div>
            <div className="mono" style={{ color: "var(--ink-3)", marginTop: 8 }}>
              {LOYALTY.toNext} to {LOYALTY.nextTier}
            </div>
          </div>
        </button>

        {/* Stat 3: Lifetime */}
        <div style={{ ...statStyles.cell, borderLeft: "1px solid var(--line)" }}>
          <div className="mono" style={{ color: "var(--ink-3)" }}>Lifetime with FOLIO</div>
          <div style={{ fontFamily: "var(--serif)", fontSize: 38, fontWeight: 300, marginTop: 14, letterSpacing: "-0.02em" }} className="num">
            €{LOYALTY.spent.toLocaleString()}
          </div>
          <div style={{ fontSize: 12, color: "var(--ink-3)", marginTop: 6 }}>
            across <span className="num" style={{ color: "var(--ink-2)" }}>{ORDERS.length}</span> orders
          </div>
          <div className="mono" style={{ marginTop: 24, color: "var(--ink-3)" }}>
            Member since {PROFILE.joined}
          </div>
        </div>
      </div>

      {/* TWO-COL: shipment tracker + activity */}
      <div style={{ display: "grid", gridTemplateColumns: "1.3fr 1fr", gap: 48, marginBottom: 48 }}>
        {/* Shipment tracker */}
        <div>
          <div style={blockHead}>
            <span className="mono" style={{ color: "var(--ink-3)" }}>Shipment</span>
            <button onClick={() => onOpenOrder(lastOrder.id)} className="mono" style={{ color: "var(--ink-2)" }}>
              Order {lastOrder.id} →
            </button>
          </div>
          <div style={{ border: "1px solid var(--line)", padding: 28 }}>
            <div style={{ display: "flex", gap: 20, marginBottom: 28 }}>
              <ProductPlaceholder label="HEAVY · CREWNECK" w={88} h={108} />
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 15, fontWeight: 500 }}>{lastOrder.items[0].name}</div>
                <div style={{ fontSize: 12, color: "var(--ink-3)", marginTop: 4 }}>{lastOrder.items[0].variant}</div>
                <div style={{ marginTop: 14, display: "flex", gap: 24, fontSize: 12 }}>
                  <div>
                    <div className="mono" style={{ color: "var(--ink-3)" }}>Carrier</div>
                    <div style={{ marginTop: 4 }}>{lastOrder.shipping.carrier}</div>
                  </div>
                  <div>
                    <div className="mono" style={{ color: "var(--ink-3)" }}>Tracking</div>
                    <div className="mono num" style={{ marginTop: 4, fontSize: 11, color: "var(--ink)" }}>{lastOrder.shipping.track}</div>
                  </div>
                  <div>
                    <div className="mono" style={{ color: "var(--ink-3)" }}>ETA</div>
                    <div style={{ marginTop: 4 }}>{lastOrder.shipping.eta}</div>
                  </div>
                </div>
              </div>
            </div>

            {/* horizontal timeline */}
            <Timeline steps={lastOrder.timeline} />
          </div>
        </div>

        {/* Activity */}
        <div>
          <div style={blockHead}>
            <span className="mono" style={{ color: "var(--ink-3)" }}>Recent activity</span>
            <span className="mono" style={{ color: "var(--ink-3)" }}>Last 30 days</span>
          </div>
          <ul style={{ listStyle: "none", border: "1px solid var(--line)" }}>
            {ACTIVITY.map((a, i) => (
              <li key={i} style={{
                display: "flex", gap: 16, padding: "16px 20px",
                borderBottom: i < ACTIVITY.length - 1 ? "1px solid var(--line)" : "none",
                fontSize: 13,
              }}>
                <span className="mono num" style={{ color: "var(--ink-3)", width: 56, flexShrink: 0 }}>{a.date}</span>
                <span style={{ color: "var(--ink-2)", lineHeight: 1.5 }}>{a.text}</span>
              </li>
            ))}
          </ul>
        </div>
      </div>

      {/* QUICK ACTIONS */}
      <div>
        <div style={blockHead}>
          <span className="mono" style={{ color: "var(--ink-3)" }}>Shortcuts</span>
        </div>
        <div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)", border: "1px solid var(--line)" }}>
          {[
            { icon: "box", label: "View all orders", target: "orders" },
            { icon: "heart", label: "Open wishlist", target: "wishlist", count: WISHLIST.length },
            { icon: "pin", label: "Manage addresses", target: "addresses" },
            { icon: "user", label: "Account details", target: "account" },
          ].map((q, i) => (
            <button key={q.target} onClick={() => onNav(q.target)} style={{
              padding: "24px 20px", textAlign: "left",
              borderRight: i < 3 ? "1px solid var(--line)" : "none",
              transition: "background 180ms ease",
              cursor: "pointer",
            }}
            onMouseEnter={(e) => e.currentTarget.style.background = "var(--bg-alt)"}
            onMouseLeave={(e) => e.currentTarget.style.background = "transparent"}>
              <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", color: "var(--ink-3)" }}>
                <Icon name={q.icon} size={16} />
                {q.count != null && <span className="mono num">{q.count}</span>}
              </div>
              <div style={{ marginTop: 32, fontSize: 13.5 }}>{q.label}</div>
              <div style={{ marginTop: 6, color: "var(--ink-3)", display: "flex", alignItems: "center", gap: 6, fontSize: 11.5 }}>
                Go <Icon name="arrow" size={11} />
              </div>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
};

const statStyles = {
  cell: { padding: "28px 28px 26px", display: "block", border: "none", background: "var(--bg)", transition: "background 220ms ease" },
};
const blockHead = { display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 14 };

// Horizontal timeline used by dashboard + order detail
const Timeline = ({ steps }) => {
  const lastDoneIdx = steps.map(s => s.done).lastIndexOf(true);
  const progress = steps.length > 1 ? lastDoneIdx / (steps.length - 1) : 0;
  return (
    <div style={{ position: "relative", padding: "8px 4px 4px" }}>
      <div style={{ position: "absolute", top: 14, left: 8, right: 8, height: 1, background: "var(--line)" }} />
      <div style={{
        position: "absolute", top: 14, left: 8,
        width: `calc(${progress * 100}% - 8px + ${progress * 0}px)`,
        maxWidth: "calc(100% - 16px)",
        height: 1, background: "var(--ink)",
        transition: "width 600ms ease",
      }} />
      <div style={{ display: "flex", justifyContent: "space-between", position: "relative" }}>
        {steps.map((s, i) => (
          <div key={i} style={{ display: "flex", flexDirection: "column", alignItems: "center", gap: 8, flex: 1 }}>
            <div style={{
              width: 10, height: 10, borderRadius: "50%",
              background: s.done ? "var(--ink)" : "var(--bg)",
              border: `1.5px solid ${s.done ? "var(--ink)" : "var(--line)"}`,
              transition: "all 180ms ease",
            }} />
            <div style={{ fontSize: 11.5, color: s.done ? "var(--ink)" : "var(--ink-3)", textAlign: "center" }}>
              {s.label}
            </div>
            <div className="mono" style={{ color: "var(--ink-3)" }}>{s.date}</div>
          </div>
        ))}
      </div>
    </div>
  );
};

// ─── ORDERS ────────────────────────────────────────────────────────
const OrdersSection = ({ onOpenOrder }) => {
  const [filter, setFilter] = useState("all");
  const filtered = useMemo(() => {
    if (filter === "all") return ORDERS;
    return ORDERS.filter(o => o.status.toLowerCase().replace(" ", "") === filter);
  }, [filter]);
  return (
    <div>
      <SectionHeader
        eyebrow="Orders / All"
        title={<><span style={{ fontStyle: "italic" }}>Your</span> orders.</>}
        sub="Every piece, from first stitch to your door."
      />

      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 20 }}>
        <div style={{ display: "flex", gap: 4, padding: 4, border: "1px solid var(--line)", borderRadius: "var(--radius-sm)" }}>
          {[
            { k: "all", label: "All" },
            { k: "intransit", label: "In transit" },
            { k: "delivered", label: "Delivered" },
            { k: "returned", label: "Returned" },
          ].map(t => (
            <button key={t.k} onClick={() => setFilter(t.k)} style={{
              padding: "6px 14px", fontSize: 12, borderRadius: "calc(var(--radius-sm) / 2)",
              background: filter === t.k ? "var(--ink)" : "transparent",
              color: filter === t.k ? "var(--bg)" : "var(--ink-2)",
              transition: "all 180ms ease",
            }}>
              {t.label}
            </button>
          ))}
        </div>
        <span className="mono" style={{ color: "var(--ink-3)" }}>{filtered.length} orders</span>
      </div>

      <div style={{ border: "1px solid var(--line)" }}>
        {/* header */}
        <div style={{ display: "grid", gridTemplateColumns: "140px 110px 1fr 100px 110px 32px", padding: "12px 24px", borderBottom: "1px solid var(--line)", background: "var(--bg-alt)" }}>
          {["Order", "Date", "Items", "Total", "Status", ""].map(h => (
            <div key={h} className="mono" style={{ color: "var(--ink-3)" }}>{h}</div>
          ))}
        </div>
        {filtered.map((o, i) => (
          <OrderRow key={o.id} order={o} last={i === filtered.length - 1} onClick={() => onOpenOrder(o.id)} />
        ))}
      </div>
    </div>
  );
};

const OrderRow = ({ order, last, onClick }) => {
  const [hover, setHover] = useState(false);
  const statusKind = { "In transit": "transit", "Delivered": "positive", "Returned": "muted" }[order.status] || "default";
  return (
    <button onClick={onClick} onMouseEnter={() => setHover(true)} onMouseLeave={() => setHover(false)} style={{
      width: "100%", textAlign: "left",
      display: "grid", gridTemplateColumns: "140px 110px 1fr 100px 110px 32px",
      padding: "20px 24px", alignItems: "center", gap: 16,
      borderBottom: last ? "none" : "1px solid var(--line)",
      background: hover ? "var(--bg-alt)" : "transparent",
      transition: "background 180ms ease",
      cursor: "pointer",
    }}>
      <span className="mono num" style={{ fontSize: 12, color: "var(--ink)" }}>{order.id}</span>
      <span style={{ fontSize: 12.5, color: "var(--ink-2)" }}>{order.date}</span>
      <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
        <div style={{ display: "flex", marginRight: 4 }}>
          {order.items.slice(0, 3).map((it, idx) => (
            <div key={idx} style={{ marginLeft: idx === 0 ? 0 : -8 }}>
              <ProductPlaceholder label="" w={36} h={44} />
            </div>
          ))}
        </div>
        <span style={{ fontSize: 13, color: "var(--ink-2)", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
          {order.items.map(i => i.name).join(", ")}
        </span>
      </div>
      <span className="num" style={{ fontSize: 13, fontWeight: 500 }}>€{order.total.toFixed(2)}</span>
      <span><Pill kind={statusKind}>{order.status}</Pill></span>
      <span style={{ color: hover ? "var(--ink)" : "var(--ink-3)", transition: "color 180ms ease, transform 180ms ease", transform: hover ? "translateX(2px)" : "none" }}>
        <Icon name="chevron" size={14} />
      </span>
    </button>
  );
};

// ─── ORDER DETAIL ──────────────────────────────────────────────────
const OrderDetailSection = ({ orderId, onBack, toast }) => {
  const order = ORDERS.find(o => o.id === orderId);
  if (!order) return null;
  const subtotal = order.items.reduce((s, i) => s + i.price * i.qty, 0);
  const shipping = order.total > 0 ? (order.total - subtotal > 0 ? order.total - subtotal : 0) : 0;
  return (
    <div>
      <button onClick={onBack} style={{ display: "inline-flex", alignItems: "center", gap: 8, color: "var(--ink-3)", fontSize: 12, marginBottom: 24 }}
        onMouseEnter={(e) => e.currentTarget.style.color = "var(--ink)"}
        onMouseLeave={(e) => e.currentTarget.style.color = "var(--ink-3)"}>
        <Icon name="arrow" size={12} stroke={1.6} /><span style={{ transform: "scaleX(-1)", display: "inline-block" }}> </span>
        <span className="mono">Back to orders</span>
      </button>

      <SectionHeader
        eyebrow={`Order ${order.id} · ${order.date}`}
        title={<><span style={{ fontStyle: "italic" }}>Order</span> {order.id}</>}
        actions={<>
          <Btn kind="ghost" icon="download" size="sm">Invoice</Btn>
          <Btn kind="ghost" size="sm" onClick={() => toast("Reorder added to bag")}>Reorder</Btn>
        </>}
      />

      {/* Status banner */}
      <div style={{ border: "1px solid var(--line)", padding: 28, marginBottom: 32, background: "var(--bg-alt)" }}>
        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 24, gap: 24 }}>
          <div>
            <Pill kind={{ "In transit": "transit", "Delivered": "positive", "Returned": "muted" }[order.status]}>{order.status}</Pill>
            <div style={{ fontFamily: "var(--serif)", fontSize: 28, fontWeight: 300, marginTop: 12, letterSpacing: "-0.01em" }}>
              {order.status === "Delivered" ? "Delivered to your door." :
               order.status === "In transit" ? `Arriving by ${order.shipping.eta}.` :
               "Return processed."}
            </div>
          </div>
          <div style={{ textAlign: "right" }}>
            <div className="mono" style={{ color: "var(--ink-3)" }}>Tracking</div>
            <div className="mono num" style={{ fontSize: 13, marginTop: 4 }}>{order.shipping.track}</div>
            <div style={{ fontSize: 12, color: "var(--ink-3)", marginTop: 4 }}>{order.shipping.carrier}</div>
          </div>
        </div>
        <Timeline steps={order.timeline} />
      </div>

      {/* Items + Summary */}
      <div style={{ display: "grid", gridTemplateColumns: "1.6fr 1fr", gap: 48 }}>
        <div>
          <div style={blockHead}><span className="mono" style={{ color: "var(--ink-3)" }}>Items</span></div>
          <div style={{ border: "1px solid var(--line)" }}>
            {order.items.map((it, i) => (
              <div key={i} style={{
                display: "flex", padding: 24, gap: 20,
                borderBottom: i < order.items.length - 1 ? "1px solid var(--line)" : "none",
              }}>
                <ProductPlaceholder label={it.sku} w={84} h={104} />
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: 14.5, fontWeight: 500 }}>{it.name}</div>
                  <div style={{ fontSize: 12, color: "var(--ink-3)", marginTop: 4 }}>{it.variant} · Qty {it.qty}</div>
                  <div className="mono" style={{ color: "var(--ink-3)", marginTop: 12 }}>SKU {it.sku}</div>
                </div>
                <div className="num" style={{ fontSize: 14, fontWeight: 500 }}>€{it.price.toFixed(2)}</div>
              </div>
            ))}
          </div>
        </div>

        <div>
          <div style={blockHead}><span className="mono" style={{ color: "var(--ink-3)" }}>Summary</span></div>
          <div style={{ border: "1px solid var(--line)", padding: 24 }}>
            <SummaryRow label="Subtotal" value={`€${subtotal.toFixed(2)}`} />
            <SummaryRow label="Shipping" value={shipping ? `€${shipping.toFixed(2)}` : "Complimentary"} />
            <SummaryRow label="Tax" value="Included" />
            <div style={{ height: 1, background: "var(--line)", margin: "16px 0" }} />
            <SummaryRow label="Total" value={`€${order.total.toFixed(2)}`} bold />
          </div>

          <div style={{ marginTop: 32 }}>
            <div className="mono" style={{ color: "var(--ink-3)", marginBottom: 12 }}>Shipping to</div>
            <div style={{ fontSize: 13, lineHeight: 1.6, color: "var(--ink-2)" }}>
              {order.address.split(", ").map((l, i) => <div key={i}>{l}</div>)}
            </div>
          </div>

          <div style={{ marginTop: 32, display: "flex", flexDirection: "column", gap: 8 }}>
            <Btn kind="ghost" icon="repeat" size="sm">Request return</Btn>
            <Btn kind="bare" size="sm">Need help with this order?</Btn>
          </div>
        </div>
      </div>
    </div>
  );
};

const SummaryRow = ({ label, value, bold }) => (
  <div style={{ display: "flex", justifyContent: "space-between", padding: "8px 0", fontSize: 13, color: bold ? "var(--ink)" : "var(--ink-2)", fontWeight: bold ? 500 : 400 }}>
    <span>{label}</span><span className="num">{value}</span>
  </div>
);

// ─── DOWNLOADS ─────────────────────────────────────────────────────
const DownloadsSection = ({ toast }) => (
  <div>
    <SectionHeader
      eyebrow="Downloads"
      title={<><span style={{ fontStyle: "italic" }}>Files</span> made for you.</>}
      sub="Care guides, lookbooks, and tailoring sheets attached to your purchases."
    />
    <div style={{ border: "1px solid var(--line)" }}>
      <div style={{ display: "grid", gridTemplateColumns: "1.6fr 130px 130px 130px 110px", padding: "12px 24px", borderBottom: "1px solid var(--line)", background: "var(--bg-alt)" }}>
        {["File", "Remaining", "Expires", "Order", ""].map(h => <div key={h} className="mono" style={{ color: "var(--ink-3)" }}>{h}</div>)}
      </div>
      {DOWNLOADS.map((d, i) => (
        <div key={d.id} style={{
          display: "grid", gridTemplateColumns: "1.6fr 130px 130px 130px 110px",
          padding: "20px 24px", alignItems: "center",
          borderBottom: i < DOWNLOADS.length - 1 ? "1px solid var(--line)" : "none",
        }}>
          <div style={{ display: "flex", alignItems: "center", gap: 16 }}>
            <div style={{ width: 40, height: 50, border: "1px solid var(--line)", display: "flex", alignItems: "center", justifyContent: "center", color: "var(--ink-3)" }}>
              <span className="mono" style={{ fontSize: 9 }}>PDF</span>
            </div>
            <div>
              <div style={{ fontSize: 13.5, fontWeight: 500 }}>{d.name}</div>
              <div className="mono" style={{ color: "var(--ink-3)", marginTop: 4 }}>{d.file}</div>
            </div>
          </div>
          <div className="num" style={{ fontSize: 12.5, color: "var(--ink-2)" }}>{d.remaining}</div>
          <div style={{ fontSize: 12.5, color: "var(--ink-2)" }}>{d.expires}</div>
          <div className="mono num" style={{ fontSize: 11, color: "var(--ink-3)" }}>{d.order}</div>
          <div>
            <Btn kind="ghost" size="sm" icon="download" onClick={() => toast(`Downloaded ${d.file}`)}>Download</Btn>
          </div>
        </div>
      ))}
    </div>
  </div>
);

// ─── WISHLIST ──────────────────────────────────────────────────────
const WishlistSection = ({ toast }) => {
  const [items, setItems] = useState(WISHLIST);
  const remove = (id) => { setItems(items.filter(i => i.id !== id)); toast("Removed from wishlist"); };
  return (
    <div>
      <SectionHeader
        eyebrow="Wishlist"
        title={<><span style={{ fontStyle: "italic" }}>Saved</span> for later.</>}
        sub="Things you've had your eye on. We'll let you know if anything goes."
      />
      <div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 24 }}>
        {items.map(w => (
          <div key={w.id} style={{ position: "relative" }}>
            <div style={{ position: "relative" }}>
              <ProductPlaceholder label={w.name.toUpperCase()} ratio="3/4" w="100%" />
              <button onClick={() => remove(w.id)} style={{
                position: "absolute", top: 10, right: 10,
                width: 28, height: 28, borderRadius: "50%",
                background: "var(--bg)", border: "1px solid var(--line)",
                display: "flex", alignItems: "center", justifyContent: "center",
                color: "var(--ink-2)", transition: "all 180ms ease",
              }}
              onMouseEnter={(e) => { e.currentTarget.style.background = "var(--ink)"; e.currentTarget.style.color = "var(--bg)"; }}
              onMouseLeave={(e) => { e.currentTarget.style.background = "var(--bg)"; e.currentTarget.style.color = "var(--ink-2)"; }}
              title="Remove">
                <Icon name="close" size={12} />
              </button>
            </div>
            <div style={{ marginTop: 14, display: "flex", justifyContent: "space-between", alignItems: "flex-start", gap: 12 }}>
              <div>
                <div style={{ fontSize: 13.5, fontWeight: 500 }}>{w.name}</div>
                <div className="mono" style={{ color: "var(--ink-3)", marginTop: 4 }}>{w.color}</div>
              </div>
              <div className="num" style={{ fontSize: 13, fontWeight: 500 }}>€{w.price.toFixed(2)}</div>
            </div>
            <div style={{ marginTop: 12, display: "flex", justifyContent: "space-between", alignItems: "center" }}>
              <span className="mono" style={{ color: w.status === "In stock" ? "var(--positive)" : w.status === "Low stock" ? "var(--warn)" : "var(--ink-3)" }}>
                · {w.status}
              </span>
              <button style={{ fontSize: 11.5, color: "var(--ink-2)", textTransform: "uppercase", letterSpacing: "0.06em", borderBottom: "1px solid var(--ink)", paddingBottom: 1 }}
                onClick={() => toast("Added to bag")}>
                Add to bag
              </button>
            </div>
          </div>
        ))}
      </div>
      {items.length === 0 && (
        <div style={{ padding: 80, textAlign: "center", color: "var(--ink-3)", border: "1px dashed var(--line)" }}>
          Your wishlist is empty.
        </div>
      )}
    </div>
  );
};

// ─── SUBSCRIPTIONS ─────────────────────────────────────────────────
const SubscriptionsSection = ({ toast }) => {
  const [subs, setSubs] = useState(SUBSCRIPTIONS);
  const toggle = (id) => {
    setSubs(subs.map(s => s.id === id ? { ...s, status: s.status === "Active" ? "Paused" : "Active" } : s));
    toast("Subscription updated");
  };
  return (
    <div>
      <SectionHeader
        eyebrow="Subscriptions"
        title={<><span style={{ fontStyle: "italic" }}>Recurring</span> services.</>}
        sub="Pause, skip, or cancel any time — no questions, no hold music."
      />
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 24 }}>
        {subs.map(s => (
          <div key={s.id} style={{ border: "1px solid var(--line)", padding: 32 }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 24 }}>
              <div>
                <div className="mono" style={{ color: "var(--ink-3)" }}>{s.cadence}</div>
                <div style={{ fontFamily: "var(--serif)", fontSize: 26, fontWeight: 300, letterSpacing: "-0.01em", marginTop: 8 }}>{s.name}</div>
              </div>
              <Pill kind={s.status === "Active" ? "positive" : "muted"}>{s.status}</Pill>
            </div>
            <div style={{ display: "flex", gap: 32, marginBottom: 24 }}>
              <div>
                <div className="mono" style={{ color: "var(--ink-3)" }}>Next charge</div>
                <div style={{ fontSize: 13.5, marginTop: 4 }}>{s.next}</div>
              </div>
              <div>
                <div className="mono" style={{ color: "var(--ink-3)" }}>Amount</div>
                <div className="num" style={{ fontSize: 13.5, marginTop: 4 }}>€{s.price.toFixed(2)}</div>
              </div>
            </div>
            <div style={{ display: "flex", gap: 8 }}>
              <Btn kind="ghost" size="sm" onClick={() => toggle(s.id)}>{s.status === "Active" ? "Pause" : "Resume"}</Btn>
              <Btn kind="bare" size="sm">Skip next</Btn>
              <Btn kind="bare" size="sm" style={{ marginLeft: "auto", color: "var(--ink-3)" }}>Cancel</Btn>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

// ─── LOYALTY ───────────────────────────────────────────────────────
const LoyaltySection = () => {
  const pct = (LOYALTY.points / (LOYALTY.points + LOYALTY.toNext)) * 100;
  return (
    <div>
      <SectionHeader
        eyebrow="Atelier rewards"
        title={<>The <span style={{ fontStyle: "italic" }}>{LOYALTY.tier}</span> tier.</>}
        sub="Earn points on every order. Unlock perks as you go."
      />

      {/* hero progress block */}
      <div style={{ border: "1px solid var(--line)", padding: "40px 40px 36px", marginBottom: 32, background: "var(--bg-alt)" }}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-end", marginBottom: 24, gap: 24 }}>
          <div>
            <div className="mono" style={{ color: "var(--ink-3)" }}>Points balance</div>
            <div style={{ fontFamily: "var(--serif)", fontSize: 64, fontWeight: 300, letterSpacing: "-0.03em", lineHeight: 1 }} className="num">
              {LOYALTY.points.toLocaleString()}
            </div>
          </div>
          <div style={{ textAlign: "right" }}>
            <div className="mono" style={{ color: "var(--ink-3)" }}>Next tier</div>
            <div style={{ fontFamily: "var(--serif)", fontSize: 28, fontWeight: 300, marginTop: 6 }}>
              {LOYALTY.nextTier}
            </div>
            <div className="mono num" style={{ color: "var(--ink-3)", marginTop: 6 }}>
              {LOYALTY.toNext} pts to go
            </div>
          </div>
        </div>

        <div style={{ position: "relative", marginTop: 28 }}>
          <div style={{ height: 3, background: "var(--line)" }} />
          <div style={{
            position: "absolute", top: 0, left: 0, height: 3,
            width: `${pct}%`, background: "var(--ink)",
            transition: "width 800ms cubic-bezier(0.2, 0.8, 0.2, 1)",
          }} />
          <div style={{ display: "flex", justifyContent: "space-between", marginTop: 14 }}>
            <div>
              <div className="mono" style={{ color: "var(--ink)" }}>{LOYALTY.tier}</div>
              <div className="mono" style={{ color: "var(--ink-3)", marginTop: 2 }}>0 pts</div>
            </div>
            <div style={{ textAlign: "right" }}>
              <div className="mono" style={{ color: "var(--ink-3)" }}>{LOYALTY.nextTier}</div>
              <div className="mono num" style={{ color: "var(--ink-3)", marginTop: 2 }}>2,500 pts</div>
            </div>
          </div>
        </div>
      </div>

      {/* perks */}
      <div style={blockHead}><span className="mono" style={{ color: "var(--ink-3)" }}>Your perks</span></div>
      <div style={{ border: "1px solid var(--line)" }}>
        {LOYALTY.perks.map((p, i) => (
          <div key={i} style={{
            display: "flex", alignItems: "center", justifyContent: "space-between",
            padding: "20px 28px",
            borderBottom: i < LOYALTY.perks.length - 1 ? "1px solid var(--line)" : "none",
            opacity: p.on ? 1 : 0.5,
          }}>
            <div style={{ display: "flex", alignItems: "center", gap: 16 }}>
              <div style={{
                width: 22, height: 22, borderRadius: "50%",
                border: `1.5px solid ${p.on ? "var(--ink)" : "var(--line)"}`,
                background: p.on ? "var(--ink)" : "transparent",
                color: "var(--bg)",
                display: "flex", alignItems: "center", justifyContent: "center",
              }}>
                {p.on && <Icon name="check" size={11} stroke={2} />}
              </div>
              <span style={{ fontSize: 14 }}>{p.label}</span>
            </div>
            {p.hint && <span className="mono" style={{ color: "var(--ink-3)" }}>Unlocks at {p.hint}</span>}
          </div>
        ))}
      </div>
    </div>
  );
};

// ─── ADDRESSES ─────────────────────────────────────────────────────
const AddressesSection = ({ toast }) => {
  const [addrs, setAddrs] = useState(ADDRESSES);
  const [editing, setEditing] = useState(null);
  const setDefault = (id) => { setAddrs(addrs.map(a => ({ ...a, default: a.id === id }))); toast("Default address updated"); };

  return (
    <div>
      <SectionHeader
        eyebrow="Addresses"
        title={<><span style={{ fontStyle: "italic" }}>Where</span> to send things.</>}
        actions={<Btn kind="primary" icon="plus" size="sm" onClick={() => setEditing("new")}>Add address</Btn>}
      />
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 24 }}>
        {addrs.map(a => (
          <div key={a.id} style={{ border: "1px solid var(--line)", padding: 28, position: "relative" }}>
            <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 20 }}>
              <div className="mono" style={{ color: "var(--ink-3)" }}>{a.label}</div>
              {a.default && <Pill kind="positive">Default</Pill>}
            </div>
            <div style={{ fontSize: 14, lineHeight: 1.65, color: "var(--ink-2)" }}>
              <div style={{ color: "var(--ink)", fontWeight: 500 }}>{a.name}</div>
              {a.lines.map((l, i) => <div key={i}>{l}</div>)}
              <div className="mono num" style={{ marginTop: 8, color: "var(--ink-3)" }}>{a.phone}</div>
            </div>
            <div style={{ display: "flex", gap: 8, marginTop: 24, paddingTop: 20, borderTop: "1px solid var(--line)" }}>
              <Btn kind="ghost" size="sm" icon="edit" onClick={() => setEditing(a.id)}>Edit</Btn>
              {!a.default && <Btn kind="bare" size="sm" onClick={() => setDefault(a.id)}>Make default</Btn>}
              <Btn kind="bare" size="sm" style={{ marginLeft: "auto", color: "var(--ink-3)" }} icon="trash">Remove</Btn>
            </div>
          </div>
        ))}

        {/* Add card */}
        <button onClick={() => setEditing("new")} style={{
          border: "1px dashed var(--line)", padding: 28,
          display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center",
          gap: 12, color: "var(--ink-3)", cursor: "pointer",
          transition: "all 180ms ease", minHeight: 220,
        }}
        onMouseEnter={(e) => { e.currentTarget.style.borderColor = "var(--ink)"; e.currentTarget.style.color = "var(--ink)"; }}
        onMouseLeave={(e) => { e.currentTarget.style.borderColor = "var(--line)"; e.currentTarget.style.color = "var(--ink-3)"; }}>
          <Icon name="plus" size={20} />
          <span className="mono">Add new address</span>
        </button>
      </div>

      {editing && <AddressEditor onClose={() => setEditing(null)} onSave={() => { setEditing(null); toast("Address saved"); }} />}
    </div>
  );
};

const AddressEditor = ({ onClose, onSave }) => (
  <Drawer onClose={onClose} title="New address">
    <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16 }}>
      <Field label="First name"><TextInput defaultValue="Margaux" /></Field>
      <Field label="Last name"><TextInput defaultValue="Béchard" /></Field>
    </div>
    <div style={{ marginTop: 16 }}>
      <Field label="Street"><TextInput placeholder="12 Rue de Turenne" /></Field>
    </div>
    <div style={{ marginTop: 16 }}>
      <Field label="Apartment, suite, etc. (optional)"><TextInput placeholder="Apt 4B" /></Field>
    </div>
    <div style={{ display: "grid", gridTemplateColumns: "1.4fr 1fr 1fr", gap: 16, marginTop: 16 }}>
      <Field label="City"><TextInput placeholder="Paris" /></Field>
      <Field label="Postal code"><TextInput placeholder="75003" /></Field>
      <Field label="Country"><TextInput placeholder="France" /></Field>
    </div>
    <div style={{ marginTop: 16 }}>
      <Field label="Phone"><TextInput placeholder="+33 …" /></Field>
    </div>
    <div style={{ marginTop: 32, display: "flex", gap: 8, justifyContent: "flex-end" }}>
      <Btn kind="ghost" onClick={onClose}>Cancel</Btn>
      <Btn kind="primary" onClick={onSave}>Save address</Btn>
    </div>
  </Drawer>
);

// ─── PAYMENTS ──────────────────────────────────────────────────────
const PaymentsSection = ({ toast }) => {
  const [pays, setPays] = useState(PAYMENTS);
  const [editing, setEditing] = useState(null);
  const setDefault = (id) => { setPays(pays.map(p => ({ ...p, default: p.id === id }))); toast("Default card updated"); };
  return (
    <div>
      <SectionHeader
        eyebrow="Payment methods"
        title={<><span style={{ fontStyle: "italic" }}>Cards</span> on file.</>}
        actions={<Btn kind="primary" icon="plus" size="sm" onClick={() => setEditing("new")}>Add card</Btn>}
      />
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 24 }}>
        {pays.map(p => (
          <div key={p.id} style={{ border: "1px solid var(--line)", padding: 0, overflow: "hidden" }}>
            <div style={{
              padding: "32px 28px 28px",
              background: p.brand === "Visa"
                ? "linear-gradient(135deg, oklch(0.22 0.015 270), oklch(0.16 0.02 265))"
                : "linear-gradient(135deg, oklch(0.32 0.04 25), oklch(0.22 0.04 25))",
              color: "oklch(0.96 0.005 90)",
              minHeight: 168,
              display: "flex", flexDirection: "column", justifyContent: "space-between",
              position: "relative",
            }}>
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
                <span className="mono" style={{ fontSize: 11, opacity: 0.65 }}>{p.brand}</span>
                {p.default && <Pill kind="muted" style={{ color: "white", borderColor: "rgba(255,255,255,0.3)" }}>Default</Pill>}
              </div>
              <div>
                <div className="mono num" style={{ fontSize: 16, letterSpacing: "0.18em", opacity: 0.95 }}>
                  •••• •••• •••• {p.last4}
                </div>
                <div style={{ display: "flex", gap: 24, marginTop: 14 }}>
                  <div>
                    <div className="mono" style={{ fontSize: 9, opacity: 0.55 }}>Holder</div>
                    <div style={{ fontSize: 12, marginTop: 3 }}>{p.holder}</div>
                  </div>
                  <div>
                    <div className="mono" style={{ fontSize: 9, opacity: 0.55 }}>Expires</div>
                    <div className="num" style={{ fontSize: 12, marginTop: 3 }}>{p.exp}</div>
                  </div>
                </div>
              </div>
            </div>
            <div style={{ display: "flex", gap: 8, padding: "16px 24px", borderTop: "1px solid var(--line)" }}>
              <Btn kind="bare" size="sm" icon="edit">Edit</Btn>
              {!p.default && <Btn kind="bare" size="sm" onClick={() => setDefault(p.id)}>Make default</Btn>}
              <Btn kind="bare" size="sm" style={{ marginLeft: "auto", color: "var(--ink-3)" }} icon="trash">Remove</Btn>
            </div>
          </div>
        ))}
      </div>

      {editing && (
        <Drawer onClose={() => setEditing(null)} title="Add a new card">
          <Field label="Card number"><TextInput placeholder="1234 5678 9012 3456" /></Field>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 16, marginTop: 16 }}>
            <Field label="Expiry"><TextInput placeholder="MM / YY" /></Field>
            <Field label="CVC"><TextInput placeholder="123" /></Field>
            <Field label="ZIP"><TextInput placeholder="75003" /></Field>
          </div>
          <div style={{ marginTop: 16 }}>
            <Field label="Cardholder name"><TextInput defaultValue="Margaux Béchard" /></Field>
          </div>
          <div style={{ marginTop: 32, display: "flex", gap: 8, justifyContent: "flex-end" }}>
            <Btn kind="ghost" onClick={() => setEditing(null)}>Cancel</Btn>
            <Btn kind="primary" onClick={() => { setEditing(null); toast("Card added"); }}>Save card</Btn>
          </div>
        </Drawer>
      )}
    </div>
  );
};

// ─── ACCOUNT DETAILS ───────────────────────────────────────────────
const AccountSection = ({ toast }) => {
  const [form, setForm] = useState(PROFILE);
  const [dirty, setDirty] = useState(false);
  const upd = (k, v) => { setForm({ ...form, [k]: v }); setDirty(true); };
  const updNL = (k, v) => { setForm({ ...form, newsletters: { ...form.newsletters, [k]: v } }); setDirty(true); };
  const updSize = (k, v) => { setForm({ ...form, size: { ...form.size, [k]: v } }); setDirty(true); };

  return (
    <div>
      <SectionHeader
        eyebrow="Account details"
        title={<><span style={{ fontStyle: "italic" }}>You,</span> on the record.</>}
        sub="Personal info, sizing, and what we send to your inbox."
      />

      {/* Profile group */}
      <FormGroup title="Profile">
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 20 }}>
          <Field label="First name"><TextInput value={form.firstName} onChange={(e) => upd("firstName", e.target.value)} /></Field>
          <Field label="Last name"><TextInput value={form.lastName} onChange={(e) => upd("lastName", e.target.value)} /></Field>
        </div>
        <div style={{ display: "grid", gridTemplateColumns: "2fr 1fr", gap: 20, marginTop: 16 }}>
          <Field label="Email"><TextInput value={form.email} onChange={(e) => upd("email", e.target.value)} /></Field>
          <Field label="Phone"><TextInput value={form.phone} onChange={(e) => upd("phone", e.target.value)} /></Field>
        </div>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 20, marginTop: 16 }}>
          <Field label="Birthday" hint="We'll send you something on it."><TextInput type="date" value={form.birthday} onChange={(e) => upd("birthday", e.target.value)} /></Field>
          <Field label="Pronouns"><TextInput value={form.pronouns} onChange={(e) => upd("pronouns", e.target.value)} /></Field>
        </div>
      </FormGroup>

      {/* Sizing */}
      <FormGroup title="Your sizes" subtitle="We use these to surface available stock first.">
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 20 }}>
          <Field label="Top"><TextInput value={form.size.top} onChange={(e) => updSize("top", e.target.value)} /></Field>
          <Field label="Bottom"><TextInput value={form.size.bottom} onChange={(e) => updSize("bottom", e.target.value)} /></Field>
          <Field label="Shoe"><TextInput value={form.size.shoe} onChange={(e) => updSize("shoe", e.target.value)} /></Field>
        </div>
      </FormGroup>

      {/* Newsletters */}
      <FormGroup title="Inbox preferences">
        {[
          { k: "drops", label: "New collections", sub: "First look when something lands." },
          { k: "lookbooks", label: "Seasonal lookbooks", sub: "Quarterly. Beautifully shot." },
          { k: "sales", label: "Sample sales & archive", sub: "Less frequent, more selective." },
        ].map(n => (
          <ToggleRow key={n.k} label={n.label} sub={n.sub} on={form.newsletters[n.k]} onChange={(v) => updNL(n.k, v)} />
        ))}
      </FormGroup>

      {/* Password */}
      <FormGroup title="Security">
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 20 }}>
          <Field label="Current password"><TextInput type="password" placeholder="••••••••" /></Field>
          <Field label="New password"><TextInput type="password" placeholder="••••••••" /></Field>
          <Field label="Confirm new password"><TextInput type="password" placeholder="••••••••" /></Field>
        </div>
      </FormGroup>

      {/* Save bar */}
      <div style={{
        position: "sticky", bottom: 0, marginTop: 32,
        background: "var(--bg)", borderTop: "1px solid var(--line)",
        padding: "20px 0", display: "flex", justifyContent: "space-between", alignItems: "center",
        opacity: dirty ? 1 : 0.5, pointerEvents: dirty ? "auto" : "none",
        transition: "opacity 220ms ease",
      }}>
        <span className="mono" style={{ color: "var(--ink-3)" }}>
          {dirty ? "You have unsaved changes" : "All changes saved"}
        </span>
        <div style={{ display: "flex", gap: 8 }}>
          <Btn kind="ghost" onClick={() => { setForm(PROFILE); setDirty(false); }}>Discard</Btn>
          <Btn kind="primary" onClick={() => { setDirty(false); toast("Changes saved"); }}>Save changes</Btn>
        </div>
      </div>
    </div>
  );
};

const FormGroup = ({ title, subtitle, children }) => (
  <section style={{ display: "grid", gridTemplateColumns: "240px 1fr", gap: 48, padding: "32px 0", borderTop: "1px solid var(--line)" }}>
    <div>
      <div style={{ fontFamily: "var(--serif)", fontSize: 22, fontWeight: 300, letterSpacing: "-0.01em" }}>{title}</div>
      {subtitle && <div style={{ fontSize: 12.5, color: "var(--ink-3)", marginTop: 8, lineHeight: 1.5 }}>{subtitle}</div>}
    </div>
    <div>{children}</div>
  </section>
);

const ToggleRow = ({ label, sub, on, onChange }) => (
  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "14px 0", borderBottom: "1px solid var(--line-2)" }}>
    <div>
      <div style={{ fontSize: 13.5 }}>{label}</div>
      <div style={{ fontSize: 12, color: "var(--ink-3)", marginTop: 3 }}>{sub}</div>
    </div>
    <button onClick={() => onChange(!on)} style={{
      width: 38, height: 22, borderRadius: 999,
      background: on ? "var(--ink)" : "var(--line)",
      position: "relative", transition: "background 220ms ease",
    }}>
      <span style={{
        position: "absolute", top: 2, left: on ? 18 : 2,
        width: 18, height: 18, borderRadius: "50%", background: "var(--bg)",
        transition: "left 220ms cubic-bezier(0.2, 0.8, 0.2, 1)",
        boxShadow: "0 1px 2px rgba(0,0,0,0.15)",
      }} />
    </button>
  </div>
);

// ─── DRAWER (modal-style for editors) ──────────────────────────────
const Drawer = ({ title, children, onClose }) => {
  useEffect(() => {
    const onKey = (e) => e.key === "Escape" && onClose();
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, []);
  return (
    <div style={{
      position: "fixed", inset: 0, zIndex: 50,
      background: "rgba(20, 20, 24, 0.4)",
      animation: "fadeIn 200ms ease",
    }} onClick={onClose}>
      <div onClick={(e) => e.stopPropagation()} style={{
        position: "absolute", top: 0, right: 0, bottom: 0,
        width: "min(560px, 92vw)", background: "var(--bg)",
        padding: "32px 40px", overflowY: "auto",
        animation: "slideIn 280ms cubic-bezier(0.2, 0.8, 0.2, 1)",
      }}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 32 }}>
          <div>
            <div className="mono" style={{ color: "var(--ink-3)" }}>Editing</div>
            <div style={{ fontFamily: "var(--serif)", fontSize: 26, fontWeight: 300, marginTop: 4 }}>{title}</div>
          </div>
          <button onClick={onClose} style={{ width: 32, height: 32, border: "1px solid var(--line)", borderRadius: "var(--radius-sm)", display: "flex", alignItems: "center", justifyContent: "center", color: "var(--ink-2)" }}>
            <Icon name="close" size={14} />
          </button>
        </div>
        {children}
      </div>
    </div>
  );
};

// ─── LOGOUT ────────────────────────────────────────────────────────
const LogoutSection = ({ onCancel }) => (
  <div style={{ maxWidth: 480, margin: "60px auto", textAlign: "center" }}>
    <div className="mono" style={{ color: "var(--ink-3)", marginBottom: 16 }}>Sign out</div>
    <h1 style={{ fontFamily: "var(--serif)", fontSize: 40, fontWeight: 300, letterSpacing: "-0.02em", lineHeight: 1.1 }}>
      <span style={{ fontStyle: "italic" }}>Until</span> next time.
    </h1>
    <p style={{ marginTop: 16, color: "var(--ink-3)", fontSize: 14 }}>
      Your wishlist and bag will still be here when you return.
    </p>
    <div style={{ marginTop: 32, display: "flex", gap: 12, justifyContent: "center" }}>
      <Btn kind="ghost" onClick={onCancel}>Stay signed in</Btn>
      <Btn kind="primary">Sign out of FOLIO</Btn>
    </div>
  </div>
);

Object.assign(window, {
  DashboardSection, OrdersSection, OrderDetailSection,
  DownloadsSection, WishlistSection, SubscriptionsSection,
  LoyaltySection, AddressesSection, PaymentsSection,
  AccountSection, LogoutSection,
});
