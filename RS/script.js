const DOM = id => document.getElementById(id);
  const currencyMask = val => '₱' + Number(val).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  
  let chartRegistry = {};
  let productLookupCache = [];

  function triggerMessage(text) {
    const banner = DOM('notification-banner');
    banner.textContent = text;
    banner.className = 'trigger';
    setTimeout(() => banner.className = '', 3500);
  }

  function switchView(target) {
    document.querySelectorAll('.viewport').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.pill-link').forEach(el => el.classList.remove('active'));
    DOM('view-' + target).classList.add('active');
    event.target.classList.add('active');
    
    if(target === 'dashboard') reloadDashboardMetrics();
    if(target === 'pos') fetchPOSContext();
    if(target === 'olap') triggerOLAPMode('rollup');
  }

  // --- RENDERING PIPELINE ENGINES ---
  async function reloadDashboardMetrics() {
    const metrics = await fetch('api/analytics.php?query=kpi').then(r => r.json());
    DOM('ind-revenue').textContent = currencyMask(metrics.revenue);
    DOM('ind-orders').textContent = metrics.orders;
    DOM('ind-covers').textContent = metrics.covers;
    DOM('ind-avg').textContent = currencyMask(metrics.avg_ticket);

    const periodData = await fetch('api/analytics.php?query=time_trend').then(r => r.json());
    renderBarChart('render-trend', periodData.map(x => x.meal_period), periodData.map(x => parseFloat(x.revenue)));

    const catData = await fetch('api/analytics.php?query=by_category').then(r => r.json());
    renderDoughnutChart('render-categories', catData.map(x => x.category), catData.map(x => parseFloat(x.revenue)));

    const journal = await fetch('api/get_data.php?type=orders').then(r => r.json());
    DOM('journal-output').innerHTML = journal.map(j => `
      <tr>
        <td style="font-weight:600; color:var(--bistro-emerald);">#TX-${j.order_id}</td>
        <td>${j.table_num}</td>
        <td>${j.server_name}</td>
        <td>${j.order_time}</td>
        <td style="font-weight:600;">${currencyMask(j.total)}</td>
      </tr>
    `).join('');
  }

  function renderBarChart(canvasId, labels, dataPoints) {
    if(chartRegistry[canvasId]) chartRegistry[canvasId].destroy();
    chartRegistry[canvasId] = new Chart(DOM(canvasId).getContext('2d'), {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{ data: dataPoints, backgroundColor: '#10b981', borderRadius: 4 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { color: '#26334d' }, ticks: { color: '#94a3b8' } },
          y: { grid: { color: '#26334d' }, ticks: { color: '#94a3b8' } }
        }
      }
    });
  }

  function renderDoughnutChart(canvasId, labels, dataPoints) {
    if(chartRegistry[canvasId]) chartRegistry[canvasId].destroy();
    chartRegistry[canvasId] = new Chart(DOM(canvasId).getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{ data: dataPoints, backgroundColor: ['#10b981', '#f59e0b', '#3b82f6', '#ec4899'], borderWidth: 0 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'right', labels: { color: '#94a3b8' } } }
      }
    });
  }

  // --- LIVE INTERACTIVE POS CODE ---
  async function fetchPOSContext() {
    const tableData = await fetch('api/get_data.php?type=tables').then(r => r.json());
    DOM('pos-table').innerHTML = '<option value="">Select Dining Station...</option>' + 
      tableData.map(t => `<option value="${t.table_id}">${t.table_num} (Seats ${t.capacity})</option>`).join('');

    productLookupCache = await fetch('api/get_data.php?type=menu').then(r => r.json());
    DOM('kitchen-inventory-output').innerHTML = productLookupCache.map(p => `
      <tr>
        <td><strong>${p.name}</strong></td>
        <td style="color:var(--bistro-muted);">${p.category}</td>
        <td>${currencyMask(p.price)}</td>
        <td><span class="chip ${p.stock < 45 ? 'stock-alert' : ''}">${p.stock} units</span></td>
      </tr>
    `).join('');

    DOM('ticket-builder-pane').innerHTML = '';
    insertTicketLine();
  }

  function insertTicketLine() {
    const lineId = Date.now();
    const options = productLookupCache.map(p => `<option value="${p.item_id}" data-cost="${p.price}">${p.name} (${currencyMask(p.price)})</option>`).join('');
    
    const wrapper = document.createElement('div');
    wrapper.className = 'ticket-line';
    wrapper.id = 'line-' + lineId;
    wrapper.innerHTML = `
      <select class="pos-item-select" onchange="recalculateTicketValue()"><option value="">Select Recipe...</option>${options}</select>
      <input type="number" class="pos-item-qty" min="1" value="1" oninput="recalculateTicketValue()" placeholder="Qty">
      <button class="trigger-delete" onclick="document.getElementById('line-${lineId}').remove(); recalculateTicketValue();">✕</button>
    `;
    DOM('ticket-builder-pane').appendChild(wrapper);
  }

  function recalculateTicketValue() {
    let aggregate = 0;
    document.querySelectorAll('.ticket-line').forEach(line => {
      const selection = line.querySelector('.pos-item-select');
      const quantity = parseInt(line.querySelector('.pos-item-qty').value) || 0;
      const staticCost = parseFloat(selection.selectedOptions[0]?.dataset.cost || 0);
      aggregate += staticCost * quantity;
    });
    DOM('pos-projection').textContent = aggregate > 0 ? `Subtotal: ${currencyMask(aggregate)}` : '';
  }

  async function commitPOSTransaction() {
    const tId = DOM('pos-table').value;
    const sName = DOM('pos-server').value.trim();
    if(!tId || !sName) { triggerMessage('Verify terminal assignments.'); return; }

    const structuralPayload = [];
    let stateValidationFlag = true;

    document.querySelectorAll('.ticket-line').forEach(line => {
      const itemVal = line.querySelector('.pos-item-select').value;
      const qtyVal = parseInt(line.querySelector('.pos-item-qty').value) || 0;
      if(!itemVal || qtyVal < 1) { stateValidationFlag = false; return; }
      structuralPayload.push({ item_id: parseInt(itemVal), quantity: qtyVal });
    });

    if(!stateValidationFlag || !structuralPayload.length) { triggerMessage('Review operational payload arrays.'); return; }

    const response = await fetch('api/place_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ table_id: tId, server_name: sName, items: structuralPayload })
    }).then(r => r.json());

    if(response.success) {
      triggerMessage(`Transaction secure. Ticket #${response.order_id} stored.`);
      fetchPOSContext();
      DOM('pos-server').value = '';
    } else {
      triggerMessage(response.error);
    }
  }

  // --- STRATIFIED OLAP METRIC MANAGEMENT ---
  function triggerOLAPMode(mode) {
    DOM('olap-block-rollup').style.display = mode === 'rollup' ? 'block' : 'none';
    DOM('olap-block-dice').style.display = mode === 'dice' ? 'block' : 'none';
    
    if(mode === 'rollup') queryRollupArchitecture();
    if(mode === 'dice') queryDicingOperation();
  }

  async function queryRollupArchitecture() {
    const dataset = await fetch('api/analytics.php?query=rollup').then(r => r.json());
    DOM('olap-rollup-matrix').innerHTML = dataset.map(row => {
      const isSub = !row.day_name;
      return `
        <tr ${isSub ? 'style="background:var(--bistro-pane); font-weight:600;"' : ''}>
          <td>${row.year_num}</td>
          <td style="color:var(--bistro-muted);">${row.month_name || '—'}</td>
          <td style="color:var(--bistro-muted);">${row.day_name || '<em>Aggregation Subtotal</em>'}</td>
          <td style="color:var(--bistro-emerald); font-weight:600;">${currencyMask(row.revenue)}</td>
        </tr>
      `;
    }).join('');
  }

  async function queryDicingOperation() {
    const period = DOM('dice-opt-period').value;
    const category = DOM('dice-opt-category').value;
    const dataset = await fetch(`api/analytics.php?query=dice&period=${period}&category=${category}`).then(r => r.json());
    
    DOM('olap-dice-matrix').innerHTML = dataset.length ? dataset.map(row => `
      <tr>
        <td><strong>${row.meal_period}</strong></td>
        <td>${row.category}</td>
        <td>${row.item}</td>
        <td style="color:var(--bistro-emerald); font-weight:600;">${currencyMask(row.revenue)}</td>
        <td>${row.units} units</td>
      </tr>
    `).join('') : '<tr><td colspan="5" style="text-align:center; color:var(--bistro-muted);">Zero fact row correlations discovered.</td></tr>';
  }

  // --- ENGINE DATA RELAYS ---
  async function fireETLPipeline() {
    const terminal = DOM('terminal-feed-output');
    const trigger = DOM('etl-execution-wire');
    
    trigger.disabled = true;
    terminal.textContent = "Pipeline executing... Extracting structural transaction snapshots...";
    
    try {
      const logs = await fetch('etl/sync.php').then(r => r.text());
      terminal.textContent = logs;
      triggerMessage('ETL extraction sequence executed successfully.');
    } catch(err) {
      terminal.textContent = "Fatal processing error encounter: " + err.message;
    }
    trigger.disabled = false;
  }

  function triggerExport(variant) {
    window.location.href = 'api/export.php?type=' + variant;
  }

  // Auto-run dashboard diagnostics on start
  reloadDashboardMetrics();
