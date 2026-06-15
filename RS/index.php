<?php
require_once 'config/auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Caba - Analytics & POS System</title>
<script src="script.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header>
  <span class="brand-logo"><span>🍽️</span> Caba Cloud Analytics</span>
  <div class="nav-pills">
    <button class="pill-link active" onclick="switchView('dashboard')">Analytics Dashboard</button>
    <button class="pill-link" onclick="switchView('pos')">Live Table Entry (POS)</button>
    <button class="pill-link" onclick="switchView('olap')">OLAP Operations</button>
    <button class="pill-link" onclick="switchView('etl')">ETL Sync Engine</button>
    <a href="logout.php" class="pill-link" style="text-decoration:none;">Logout (<?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>)</a>
  </div>
</header>

<div id="notification-banner">System Alert Generated</div>

<div class="viewport active" id="view-dashboard">
  <div class="view-heading">Operational Performance Indices</div>
  <div class="grid-metrics">
    <div class="card-metric emerald">
      <div class="metric-desc">Gross F&amp;B Revenue</div>
      <div class="metric-stat" id="ind-revenue">₱0.00</div>
    </div>
    <div class="card-metric">
      <div class="metric-desc">Total F&amp;B Checks</div>
      <div class="metric-stat" id="ind-orders">0</div>
    </div>
    <div class="card-metric">
      <div class="metric-desc">Covers Prepared (Qty)</div>
      <div class="metric-stat" id="ind-covers">0</div>
    </div>
    <div class="card-metric gold">
      <div class="metric-desc">Average Spend Per Ticket</div>
      <div class="metric-stat" id="ind-avg">₱0.00</div>
    </div>
  </div>

  <div class="view-heading">Analytical Matrix Output</div>
  <div class="layout-flex">
    <div class="panel-bistro">
      <div class="panel-title">Revenue Extraction via Meal Period</div>
      <div class="panel-subtitle">OLAP Core Slice: Filtered by business hours timeline dimension</div>
      <div class="canvas-container"><canvas id="render-trend"></canvas></div>
    </div>
    <div class="panel-bistro">
      <div class="panel-title">Gross Volume Breakdown via Item Categories</div>
      <div class="panel-subtitle">Dimensional analysis across product grouping categories</div>
      <div class="canvas-container"><canvas id="render-categories"></canvas></div>
    </div>
  </div>

  <div class="view-heading" style="display:flex; justify-content:space-between; align-items:center;">
    <span>Recent Transaction Journals</span>
    <div style="display:flex; gap:8px;">
      <button class="action-trigger trigger-outline" onclick="triggerExport('orders')">Export Transaction Logs (CSV)</button>
      <button class="action-trigger trigger-outline" onclick="triggerExport('sales_fact')">Export Fact Matrix (CSV)</button>
    </div>
  </div>
  <div class="panel-bistro">
    <div class="scroll-x">
      <table id="journal-table">
        <thead>
          <tr><th>Order Ledger ID</th><th>Table Target</th><th>Assigned Server</th><th>Transaction Time</th><th>Financial Value</th></tr>
        </thead>
        <tbody id="journal-output">
          <tr><td colspan="5" style="text-align:center; color:var(--bistro-muted);">Querying operational stream...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="viewport" id="view-pos">
  <div class="view-heading">Register Client Ticket (OLTP Live Context)</div>
  <div class="panel-bistro" style="max-width:760px; margin-bottom:32px;">
    <div class="input-matrix">
      <div class="input-housing">
        <label>Target Table Mapping</label>
        <select id="pos-table"><option value="">Awaiting Map...</option></select>
      </div>
      <div class="input-housing">
        <label>Assigned Lead Server</label>
        <input type="text" id="pos-server" placeholder="Enter assigned server name">
      </div>
    </div>

    <div class="view-heading" style="margin-top:16px;">Ticket Items Breakout</div>
    <div id="ticket-builder-pane"></div>
    
    <button class="action-trigger trigger-outline" onclick="insertTicketLine()" style="margin-bottom:24px;">+ Insert Item Row</button>
    
    <div style="display:flex; gap:12px; align-items:center;">
      <button class="action-trigger trigger-primary" onclick="commitPOSTransaction()">Commit Operational Transaction</button>
      <span id="pos-projection" style="font-weight:600; color:var(--bistro-emerald); font-family:var(--font-heading);"></span>
    </div>
  </div>

  <div class="view-heading">Kitchen Inventory Matrix</div>
  <div class="panel-bistro">
    <div class="scroll-x">
      <table>
        <thead>
          <tr><th>Menu Item Title</th><th>Classification Category</th><th>Unit Valuation</th><th>Remaining Inventory Qty</th></tr>
        </thead>
        <tbody id="kitchen-inventory-output"></tbody>
      </table>
    </div>
  </div>
</div>

<div class="viewport" id="view-olap">
  <div class="view-heading">Dimensional OLAP Interrogation</div>
  <div style="display:flex; gap:10px; margin-bottom:24px;" id="olap-selectors">
    <button class="action-trigger trigger-outline" onclick="triggerOLAPMode('rollup')">Hierarchical Roll-up Matrix</button>
    <button class="action-trigger trigger-outline" onclick="triggerOLAPMode('dice')">Simultaneous Multi-Dimensional Dice</button>
  </div>

  <div id="olap-block-rollup" class="panel-bistro">
    <div class="panel-title">Multi-Tier Hierarchical Roll-Up Output</div>
    <div class="panel-subtitle">Demonstrates structured multidimensional groupings tracking Year ➔ Month ➔ Weekday. Subtotals automatically generate where rows are NULL.</div>
    <div class="scroll-x">
      <table>
        <thead><tr><th>Target Year</th><th>Target Month</th><th>Identified Weekday</th><th>Total Extracted Revenue</th></tr></thead>
        <tbody id="olap-rollup-matrix"></tbody>
      </table>
    </div>
  </div>

  <div id="olap-block-dice" class="panel-bistro" style="display:none;">
    <div class="panel-title">Multi-Axis Slicing Configuration (Dice)</div>
    <div class="panel-subtitle">Filters multiple dimensions concurrently across the Fact Matrix records.</div>
    <div class="input-matrix" style="margin-top:16px; margin-bottom:24px;">
      <div class="input-housing">
        <label>Time Shift Category Slice</label>
        <select id="dice-opt-period" onchange="queryDicingOperation()">
          <option value="">All Operational Periods</option>
          <option>Breakfast</option><option>Lunch</option><option>Dinner</option><option>Late Night</option>
        </select>
      </div>
      <div class="input-housing">
        <label>Item Category Grouping Slice</label>
        <select id="dice-opt-category" onchange="queryDicingOperation()">
          <option value="">All Menu Groupings</option>
          <option>Appetizers</option><option>Main Course</option><option>Desserts</option><option>Beverages</option>
        </select>
      </div>
    </div>
    <div class="scroll-x">
      <table>
        <thead><tr><th>Meal Phase</th><th>Category Group</th><th>Target Recipe Item</th><th>Segment Revenue</th><th>Units Swapped</th></tr></thead>
        <tbody id="olap-dice-matrix"></tbody>
      </table>
    </div>
  </div>
</div>

<div class="viewport" id="view-etl">
  <div class="view-heading">OLTP Extraction Pipeline Engine</div>
  <div class="panel-bistro" style="max-width:720px;">
    <p style="color:var(--bistro-muted); margin-bottom:20px; line-height:1.6;">
      This control panel executes the pipeline sequence that converts operational customer receipts into organized structural multidimensional logs inside the <strong>fact_restaurant_sales</strong> repository. Run this execution loop after simulating high-volume operations inside the terminal.
    </p>
    <button class="action-trigger trigger-primary" id="etl-execution-wire" onclick="fireETLPipeline()">Initialize Real-Time ETL Pipeline Cycle</button>
    <div class="view-heading" style="margin-top:24px;">ETL Diagnostics Terminal Feed</div>
    <div class="terminal-log" id="terminal-feed-output">System engine idle. Dispatched to await execution routines.</div>
  </div>
</div>

</body>
</html>