<div style="max-width: 640px; margin: 0 auto;">
    <div style="margin-bottom: 1.5rem;">
        <a href="<?= url('/hr/positions') ?>" style="text-decoration: none; color: #4F46E5; font-size: 0.875rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem;">
            ← Back to Positions Directory
        </a>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--color-text-main, #1E293B); margin: 0.5rem 0 0.25rem 0;">Edit Job Position</h1>
        <p style="color: var(--color-text-muted, #64748B); margin: 0; font-size: 0.875rem;">Update position title or department assignment.</p>
    </div>

    <div class="card" style="background: var(--color-bg-surface, #FFFFFF); border: 1px solid var(--color-border, #E2E8F0); border-radius: 0.75rem; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <form method="POST" action="<?= url('/hr/positions/' . $position['id'] . '/edit') ?>">
            <?= csrf_field() ?>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="posDept" class="form-label required" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: var(--color-text-main, #1E293B);">
                    Assigned Department
                </label>
                <select id="posDept" 
                        name="department_id" 
                        class="form-input" 
                        style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid <?= !empty($errors['department_id']) ? '#EF4444' : '#CBD5E1' ?>; border-radius: 0.5rem; font-size: 0.875rem; background: var(--color-bg-surface, #FFFFFF); box-sizing: border-box;"
                        required>
                    <option value="">-- Select Department --</option>
                    <?php 
                    $currentDeptId = $old['department_id'] ?? $position['department_id'];
                    foreach ($departments as $dept): ?>
                        <option value="<?= e($dept['id']) ?>" <?= ($currentDeptId == $dept['id']) ? 'selected' : '' ?>>
                            <?= e($dept['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['department_id'])): ?>
                    <div style="color: #EF4444; font-size: 0.8125rem; margin-top: 0.25rem;">
                        <?= e($errors['department_id']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="posName" class="form-label required" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: var(--color-text-main, #1E293B);">
                    Position Title
                </label>
                <input type="text" 
                       id="posName" 
                       name="position_name" 
                       class="form-input" 
                       style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid <?= !empty($errors['position_name']) ? '#EF4444' : '#CBD5E1' ?>; border-radius: 0.5rem; font-size: 0.875rem; box-sizing: border-box;"
                       value="<?= e($old['position_name'] ?? $position['position_name']) ?>" 
                       required>
                <?php if (!empty($errors['position_name'])): ?>
                    <div style="color: #EF4444; font-size: 0.8125rem; margin-top: 0.25rem;">
                        <?= e($errors['position_name']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group" style="margin-bottom: 1.75rem;">
                <label for="posDesc" class="form-label" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: var(--color-text-main, #1E293B);">
                    Description (Optional)
                </label>
                <textarea id="posDesc" 
                          name="description" 
                          class="form-input" 
                          rows="4" 
                          style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid var(--color-border-dark, #CBD5E1); border-radius: 0.5rem; font-size: 0.875rem; box-sizing: border-box; resize: vertical;"><?= e($old['description'] ?? $position['description'] ?? '') ?></textarea>
                <?php if (!empty($errors['description'])): ?>
                    <div style="color: #EF4444; font-size: 0.8125rem; margin-top: 0.25rem;">
                        <?= e($errors['description']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; border-top: 1px solid var(--color-border-subtle, #F1F5F9); padding-top: 1.25rem;">
                <a href="<?= url('/hr/positions') ?>" class="btn btn-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary" style="padding: 0.625rem 1.25rem; font-weight: 600;">
                    Update Position
                </button>
            </div>
        </form>
    </div>
</div>
