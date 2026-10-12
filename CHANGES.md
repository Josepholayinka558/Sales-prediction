# What changed in this version

## New: Sales Prediction page (menu -> "Sales Prediction")
Pick a YEAR and a PRODUCT (or a whole category, or all products) and get predicted sales (money and units) month by month,
with a likely range, a back-test accuracy score, CSV/Excel export, and a table predicting every product for that year.
- Months already in your data are shown as ACTUAL; later months are PREDICTED.
- With under 12 months of history the model uses your recent run-rate (it cannot learn seasons yet). With 12-24+ months it learns seasonality and trend. The page tells you which it used.

## Fixed
- Dashboard showed fake demo numbers -> now 100% real data (KPIs, yearly bars, categories, top products, latest sales, prediction preview).
- Monthly/yearly charts were empty because they looked at "last 12 months from today" -> now anchored to your data.
- Tables (Sales, Orders, Products, Customers, Users) only loaded the first 25 rows -> now fully paged/searchable/sortable over all rows.
- "Show all" and default sorting crashed the Sales table.
- Delete / View buttons only pretended to work -> now real (with permission + CSRF checks).
- Users page: Add user was broken; Edit and Reset password were placeholders -> all work (temporary password shown once).
- Reports: Generate never saved; Export ignored the date range -> real reports, real CSV/Excel downloads.
- Sales page Export CSV/Excel did nothing -> real downloads honouring the filters.
- Chat replied with page HTML mixed in (unreadable) and forecast said $0 -> fixed, uses the real prediction.
- Analytics: "Refresh" crashed charts, heatmap was fake -> fixed / real.
- Forecast page saved 2 history rows on every page load, and Delete did nothing -> fixed.
- Settings: made-up table counts, fake backup download and fake restore -> real counts, real download, honest restore instructions.
- Header: dead search box, Pin, Share, fixed fake date -> search works, Share copies link, date shows your data range.
- Menu now hides pages your role cannot open (no more "Access denied" surprises); added Sales, Sales Prediction and Assistant links.
- Importer accepts 0-quantity rows and spreadsheet column names like the CSV path; XLSX parser handles absolute sheet paths.

## Data
sample-data/supermarket_sales_clean.csv  (10,000 rows, import this one; tick "truncate" to replace old data)
sample-data/supermarket_sales_clean.xlsx (same data)
