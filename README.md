# TradeVision Pro

**Real-Time Market Scanner & Professional Trading Signals Powered By Live Market Data.**

A complete, production-ready trading SaaS for standard cPanel shared hosting.
Pure **PHP 8.2+ / MySQL 8** backend, **vanilla JS + Alpine.js + Tailwind (CDN) + Chart.js + TradingView**
frontend. No Node, npm, build step, Docker, or AI.

> 🔒 **No AI. No LLMs. No generated signals.** Every scan, zone, structure read and signal is
> produced from live market data using technical indicators and mathematical rules only.

---

## Features

| # | Feature | Highlights |
|---|---------|-----------|
| 1 | **Real-Time Scanner** | All Binance Futures pairs, 1m/5m/15m/1h, setups: breakout, reversal, pullback, range break, volume surge, volatility expansion, liquidity sweep, trend continuation. Rated Strong Buy → Strong Sell. |
| 2 | **Technical Analysis Engine** | RSI, MACD, EMA 20/50/100/200, SMA, VWAP, Bollinger Bands, ATR, Stochastic RSI, OBV, Volume Profile. |
| 3 | **Market Structure** | HH/HL/LH/LL labelling, trend detection, numeric trend score. |
| 4 | **Supply & Demand** | Auto zone detection, fresh/tested/mitigated states, historical storage. |
| 5 | **Smart Money Concepts** | BOS, CHOCH, liquidity sweeps, equal highs/lows, FVGs, order blocks, breaker blocks, premium/discount. |
| 6 | **Signal Engine** | Multi-confirmation, weighted confidence 0–100 (trend 25 / volume 20 / RSI 15 / structure 20 / S&D 20). Entry, SL, TP1–3, R:R. Scalping/Intraday/Swing. |
| 7 | **Trading Terminal** | TradingView chart + live analysis panels. |
| 8 | **Watchlists** | Crypto, forex, stocks, commodities. |
| 9 | **Alerts** | Browser, Email, Telegram. |
| 10 | **Trade Journal** | Screenshots, notes, win rate, avg R:R, profit factor, total PnL. |
| 11 | **Portfolio Tracker** | Open/closed trades, PnL, drawdown, equity curve. |
| 12 | **Manual USDT Payments** | BEP20, TXID + screenshot, admin approval, auto-activation. |
| ➕ | **Admin Panel** | Users, payments, wallets, settings, scanner weights, announcements, logs. |

---

## Architecture

```
public_html/
├── index.php              Landing page
├── login.php register.php Auth
├── dashboard/ terminal/ scanner/ signals/ watchlists/
├── journal/ portfolio/ alerts/ pricing/ admin/
├── api/                   REST API (front controller + routes/)
│   ├── index.php          Router: /api/{resource}/{action}/{id}
│   └── routes/*.php
├── includes/
│   ├── Database, Security, JWT, Auth, Response, Helpers, bootstrap
│   └── engine/            MarketData, Indicators, MarketStructure,
│                          SupplyDemand, SMC, Scanner, SignalEngine, AlertDispatcher
├── cron/                  scan.php, signals_update.php, alerts.php, maintenance.php
├── config/  uploads/  storage/  assets/
database/tradevision_schema.sql
```

**Data flow:** cron → `MarketData` (cached) → `Indicators` + `MarketStructure` + `SupplyDemand` + `SMC`
→ `Scanner` (ratings, persisted) → `SignalEngine` (weighted confidence) → DB → REST API → frontend.

---

## Quick start

See **[DEPLOYMENT.md](DEPLOYMENT.md)** for the full cPanel guide. In short:

1. Upload `public_html/` contents to cPanel.
2. Create a MySQL DB and import `database/tradevision_schema.sql`.
3. Edit `public_html/config/config.php` (DB creds + `JWT_SECRET`).
4. Add the cron jobs from `public_html/cron/crontab.example.txt`.
5. Visit your site; log into `/admin/` (`admin@tradevision.pro` / `Admin@12345`) and **change the password**.

---

## Subscription plans

| Plan | Price | Scanner | Signals | Watchlists | Alerts |
|------|-------|---------|---------|-----------|--------|
| Free | $0 | Limited | 5/day | 1 | 3 |
| Pro | $29/mo USDT | Full | Unlimited | 10 | 100 |
| Elite | $79/mo USDT | Full + Analytics | Unlimited + Priority | 50 | 1000 |

---

## Security

CSRF tokens · XSS escaping · PDO prepared statements · bcrypt hashing · JWT auth ·
hardened sessions · upload validation · rate limiting · security headers + CSP ·
deny-all on `config/`, `includes/`, `storage/` · no script execution in `uploads/`.

## Disclaimer

TradeVision Pro provides analytical tools only and **is not financial advice**.
Trading involves substantial risk. Past performance does not guarantee future results.
