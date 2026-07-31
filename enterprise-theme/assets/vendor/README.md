# Vendor assets

This directory contains third-party JavaScript libraries bundled with
ParsYar so the theme has **zero runtime CDN dependencies**.

| File | Upstream | License | Notes |
|------|----------|---------|-------|
| `dayjs/dayjs.min.js` | [dayjs 1.11.10](https://day.js.org/) | MIT | Minimal date library. |
| `dayjs/dayjs-jalali.min.js` | self-bundled | MIT | Minimal Jalali plugin for dayjs (33-year cycle). |
| `sortable.min.js` | [SortableJS 1.15.2](https://github.com/SortableJS/Sortable) | MIT | Drag & drop (kanban). |
| `tippy.min.js` | [Tippy.js 6.3.7](https://atomiks.github.io/tippyjs/) | MIT | Tooltips & popovers. |
| `chart.js/chart.umd.min.js` | [Chart.js 4.4.0](https://www.chartjs.org/) | MIT | Charts. |

To refresh a vendor file, re-download from a trusted mirror:

```bash
curl -sL "https://cdn.jsdelivr.net/npm/<name>@<ver>/<path>" -o <local-path>
```

**Never** replace a vendor file without bumping the version constant in
`functions.php` so cache invalidation works.
