# Infinite Scroll — Public API

The module currently exposes no PHP or JS extension hooks. Extension hooks for selector override (`freeman_core/infinite_scroll/selector`) and render-bracket actions (`freeman_core/infinite_scroll/before_render`, `freeman_core/infinite_scroll/after_render`) are planned for Wave 3.1b — see [/docs/wave-3.1-master-plan.md](../../../../docs/wave-3.1-master-plan.md) §4-D7 for the resolved signatures. Documentation will land in this file when the hooks ship.

## Template overrides

Skeleton card markup is inline in `assets/js/infinite-scroll.js` (`makeSkeletonCard()`). No filter override exists today.
