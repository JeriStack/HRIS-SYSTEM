<?php
/**
 * HR Workforce Leave Balances, SIL Cash Conversion Records & Ledger Directory
 */
$selectedYear = (int)($selectedYear ?? date('Y'));
$selectedDept = $selectedDept ?? null;
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Workforce Leave Balances (<?= $selectedYear ?>)</h1>
        <p>Inspect employee annual entitlements, adjust quotas with audit rationale, and record SIL cash-conversion memos.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/hr/leave-qualifications') ?>" class="btn btn-secondary btn-sm">
            <?= icon('check-circle', 15) ?>
            <span>Leave Qualifications (SPIC / Marriage)</span>
        </a>
        <a href="<?= url('/hr/leave-types') ?>" class="btn btn-secondary btn-sm">
            <?= icon('layers', 15) ?>
            <span>Leave Policies</span>
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card" style="margin-bottom: var(--space-5); padding: var(--space-4);">
    <form method="GET" action="<?= url('/leaves/balances') ?>" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end; width: 100%;">
        <div>
            <label for="yearFilter" class="form-label" style="font-size: var(--font-size-xs);">Balance Year</label>
            <select name="year" id="yearFilter" class="form-select" style="padding: 6px 10px;">
                <?php for ($y = (int)date('Y') + 1; $y >= (int)date('Y') - 2; $y--): ?>
                    <option value="<?= $y ?>" <?= $selectedYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label for="deptFilter" class="form-label" style="font-size: var(--font-size-xs);">Department</label>
            <select name="dept" id="deptFilter" class="form-select" style="padding: 6px 10px;">
                <option value="">All Departments</option>
                <?php foreach ($departments ?? [] as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= (int)$selectedDept === (int)$d['id'] ? 'selected' : '' ?>>
                        <?= e((string)$d['department_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
        <a href="<?= url('/leaves/balances') ?>" class="btn btn-secondary btn-sm">Reset</a>
    </form>
</div>

<!-- Workforce Balances Table -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 class="card-title">Annual Leave Balance Registry (<?= count($balances ?? []) ?> rows)</h2>
    </div>
    <div class="table-wrapper">
        <table class="table table-hover" aria-label="Workforce Leave Balances Table">
            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Employee Name</th>
                    <th>Department</th>
                    <th>Leave Classification</th>
                    <th>Allocated Days</th>
                    <th>Days Used</th>
                    <th>Converted</th>
                    <th>Remaining Days</th>
                    <th>Utilization Meter</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($balances)): ?>
                    <tr>
                        <td colspan="9" class="text-center" style="padding: 2rem; color: var(--color-text-muted);">
                            No leave balance records found for the selected filters.
                        </td>
                    </tr>
                <?php else: foreach ($balances as $b):
                    $alloc = (int)($b['allocated_days'] ?? 0);
                    $used = (int)($b['used_days'] ?? 0);
                    $conv = (int)round((float)($b['converted_days'] ?? 0));
                    $rem = (int)($b['remaining_days'] ?? 0);
                    $pct = $alloc > 0 ? min(100, (int)round((($used + $conv) / $alloc) * 100)) : 0;
                    $empName = trim((string)($b['first_name'] ?? '') . ' ' . (string)($b['last_name'] ?? ''));
                    $isSilVl = !empty($b['sil_credited_from_vl']);
                ?>
                    <tr>
                        <td style="font-family: var(--font-mono); font-size: var(--font-size-xs);"><?= e((string)($b['employee_no'] ?? '—')) ?></td>
                        <td><strong><?= e($empName) ?></strong></td>
                        <td><?= e((string)($b['department_name'] ?? 'General')) ?></td>
                        <td>
                            <div style="font-weight: 500;"><?= e((string)($b['leave_name'] ?? '')) ?></div>
                            <?php if ($isSilVl): ?>
                                <span class="badge badge-info" style="font-size: 0.6875rem;">Satisfied via Qualifying VL</span>
                            <?php elseif (!empty($b['classification'])): ?>
                                <span class="badge <?= $b['classification'] === 'Statutory' ? 'badge-statutory' : 'badge-company' ?>" style="font-size: 0.6875rem;">
                                    <?= e((string)$b['classification']) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= $alloc ?></td>
                        <td><?= $used ?></td>
                        <td><?= $conv ?></td>
                        <td><strong style="color: <?= $rem <= 2 ? 'var(--color-warning)' : 'var(--color-primary)' ?>;"><?= $rem ?></strong></td>
                        <td style="min-width: 120px;">
                            <div style="height: 6px; background: var(--color-bg-muted); border-radius: 999px; overflow: hidden;">
                                <div style="width: <?= $pct ?>%; height: 100%; background: var(--color-primary);"></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (can('leave_balances.manage')): ?>
<!-- HR Management Actions Grid: Quota Adjustment, Initialize Balances, SIL Cash Conversion -->
<div class="grid grid-cols-2" style="gap: var(--space-5); margin-bottom: var(--space-6); align-items: start;">
    <div class="card">
        <div class="card-header"><h2 class="card-title">Adjust Employee Quota Allocation</h2></div>
        <div class="card-body">
            <form method="POST" action="<?= url('/hr/leaves/balances/adjust') ?>" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                <?= csrf_field() ?>
                <input type="hidden" name="year" value="<?= $selectedYear ?>">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="adj_employee_id" class="form-label">Employee</label>
                    <select name="employee_id" id="adj_employee_id" class="form-select" required>
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees ?? [] as $emp): ?>
                            <option value="<?= (int)$emp['id'] ?>">
                                <?= e(($emp['employee_no'] ?? '') . ' — ' . ($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="adj_leave_type_id" class="form-label">Leave Classification</label>
                    <select name="leave_type_id" id="adj_leave_type_id" class="form-select" required>
                        <option value="">-- Select Type --</option>
                        <?php foreach ($leaveTypes ?? [] as $lt):
                            if (($lt['entitlement_model'] ?? 'annual') !== 'annual') {
                                continue;
                            }
                        ?>
                            <option value="<?= (int)$lt['id'] ?>"><?= e((string)$lt['leave_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="adj_allocated_days" class="form-label">New Allocated Quota (Days)</label>
                    <input type="number" name="allocated_days" id="adj_allocated_days" class="form-control" min="0" max="365" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="adj_reason" class="form-label">Adjustment Audit Rationale</label>
                    <input type="text" name="reason" id="adj_reason" class="form-control" placeholder="e.g. Approved tenure credit or policy correction" required>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Update Quota &amp; Log Ledger</button>
            </form>

            <hr style="border: 0; border-top: 1px solid var(--color-border-subtle); margin: var(--space-4) 0;">

            <form method="POST" action="<?= url('/hr/leaves/balances/initialize') ?>" style="display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap;">
                <?= csrf_field() ?>
                <input type="hidden" name="year" value="<?= $selectedYear ?>">
                <div class="form-group" style="flex: 1; min-width: 220px; margin-bottom: 0;">
                    <label for="init_employee_id" class="form-label">Initialize Eligible Annual Balances (<?= $selectedYear ?>)</label>
                    <select name="employee_id" id="init_employee_id" class="form-select" required>
                        <option value="">-- Select Employee to Initialize --</option>
                        <?php foreach ($employees ?? [] as $emp): ?>
                            <option value="<?= (int)$emp['id'] ?>">
                                <?= e(($emp['employee_no'] ?? '') . ' — ' . ($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary btn-sm">Initialize Missing Quotas</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Record SIL Cash Conversion Reference (Art. 95)</h2></div>
        <div class="card-body">
            <?php if (empty($silType)): ?>
                <p style="color: var(--color-text-muted); margin: 0;">No Service Incentive Leave (SIL) classification configured yet.</p>
            <?php else: ?>
                <p style="font-size: var(--font-size-xs); color: var(--color-text-muted); margin-top: 0;">
                    Records approved annual unused SIL commutation or separation-related conversion references for payroll handoff (no salary calculation performed in HRIS).
                </p>
                <form method="POST" action="<?= url('/hr/leaves/sil-conversion') ?>" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 1rem; align-items: end;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="year" value="<?= $selectedYear ?>">
                    <input type="hidden" name="leave_type_id" value="<?= (int)$silType['id'] ?>">

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="silConvEmp" class="form-label">Employee</label>
                        <select id="silConvEmp" name="employee_id" class="form-select" required>
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($employees ?? [] as $emp): ?>
                                <option value="<?= (int)$emp['id'] ?>">
                                    <?= e(($emp['employee_no'] ?? '') . ' — ' . ($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="silConvScope" class="form-label">Conversion Scope</label>
                        <select id="silConvScope" name="conversion_scope" class="form-select" required>
                            <option value="annual_unused">Year-End Unused SIL Commutation</option>
                            <option value="separation">Separation-Related SIL Conversion</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="silConvDays" class="form-label">Days to Convert</label>
                        <input type="number" id="silConvDays" name="days" class="form-input" min="1" max="30" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="silConvRef" class="form-label">HR Memo / Reference #</label>
                        <input type="text" id="silConvRef" name="conversion_reference" class="form-input" maxlength="80" placeholder="e.g. SIL-COMM-2026-019" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 0; grid-column: 1 / -1;">
                        <label for="silConvRemarks" class="form-label">Remarks (Optional)</label>
                        <input type="text" id="silConvRemarks" name="remarks" class="form-input" maxlength="200" placeholder="Additional clearance or separation note">
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">Record Conversion Reference</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Immutable Transaction Ledger -->
<div class="card">
    <div class="card-header"><h2 class="card-title">Recent Leave Balance &amp; Conversion Ledger (<?= $selectedYear ?>)</h2></div>
    <div class="table-wrapper">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Employee</th>
                    <th>Classification</th>
                    <th>Transaction</th>
                    <th>Delta</th>
                    <th>Before &rarr; After</th>
                    <th>Reference</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentLedger)): ?>
                    <tr><td colspan="8" class="text-center" style="color: var(--color-text-muted); padding: 1.5rem;">No ledger entries recorded for <?= $selectedYear ?> yet.</td></tr>
                <?php else: foreach ($recentLedger as $tx):
                    $delta = (float)($tx['days_delta'] ?? 0);
                    $deltaStr = ($delta > 0 ? '+' : '') . rtrim(rtrim(number_format($delta, 2, '.', ''), '0'), '.');
                ?>
                    <tr>
                        <td style="white-space: nowrap;"><?= !empty($tx['created_at']) ? e(date('M d, Y H:i', strtotime((string)$tx['created_at']))) : '—' ?></td>
                        <td>
                            <strong><?= e(trim(($tx['first_name'] ?? '') . ' ' . ($tx['last_name'] ?? ''))) ?></strong>
                            <div style="font-size: var(--font-size-xs); color: var(--color-text-muted);"><?= e((string)($tx['employee_no'] ?? '')) ?></div>
                        </td>
                        <td><?= e((string)($tx['leave_name'] ?? '')) ?></td>
                        <td><span class="badge badge-neutral"><?= e((string)($tx['transaction_type'] ?? '')) ?></span></td>
                        <td class="<?= $delta >= 0 ? 'ledger-delta-pos' : 'ledger-delta-neg' ?>"><?= e($deltaStr) ?>d</td>
                        <td><?= (int)round((float)($tx['balance_before'] ?? 0)) ?>d &rarr; <?= (int)round((float)($tx['balance_after'] ?? 0)) ?>d</td>
                        <td><?= e((string)($tx['conversion_reference'] ?? (!empty($tx['leave_request_id']) ? '#LR-' . $tx['leave_request_id'] : '—'))) ?></td>
                        <td><?= e((string)($tx['remarks'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
