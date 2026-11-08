<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SkeletonRendererTest extends TestCase
{
    public function test_render_skeleton_returns_empty_string_for_unknown_component(): void
    {
        $output = render_skeleton('non_existent_component_xyz');
        $this->assertSame('', $output);
    }

    public function test_render_skeleton_wraps_in_accessible_container_with_role_status(): void
    {
        $output = render_skeleton('card', ['loading_text' => 'Fetching records...']);
        $this->assertStringContainsString('role="status"', $output);
        $this->assertStringContainsString('aria-busy="true"', $output);
        $this->assertStringContainsString('<span class="sr-only">Fetching records...</span>', $output);
        $this->assertStringContainsString('skeleton-container', $output);
    }

    public function test_render_skeleton_raw_mode_omits_container_wrapper(): void
    {
        $output = render_skeleton('card', ['raw' => true]);
        $this->assertStringNotContainsString('skeleton-container', $output);
        $this->assertStringNotContainsString('role="status"', $output);
        $this->assertStringContainsString('skeleton-card', $output);
    }

    public function test_render_skeleton_stat_card_renders_correct_number_of_cards(): void
    {
        $output = render_skeleton('stat_card', ['count' => 3]);
        $matches = substr_count($output, 'skeleton-stat-card');
        $this->assertSame(3, $matches);
        $this->assertStringContainsString('skeleton-stat-grid', $output);
        $this->assertStringContainsString('skeleton-icon', $output);
    }

    public function test_render_skeleton_table_renders_requested_rows_and_columns(): void
    {
        $rows = 4;
        $cols = 6;
        $output = render_skeleton('table', [
            'rows' => $rows,
            'columns' => $cols,
            'hasToolbar' => true
        ]);

        $this->assertStringContainsString('skeleton-table-wrapper', $output);
        $this->assertStringContainsString('skeleton-table-toolbar', $output);
        $this->assertSame($cols, substr_count($output, '<th>'));
        $this->assertSame($rows, substr_count($output, '<tr>') - 1); // minus the thead tr
    }

    public function test_render_skeleton_card_renders_requested_lines(): void
    {
        $output = render_skeleton('card', [
            'lines' => 4,
            'hasHeader' => true,
            'hasFooter' => true,
            'hasAvatar' => true
        ]);

        $this->assertStringContainsString('skeleton-card-header', $output);
        $this->assertStringContainsString('skeleton-card-body', $output);
        $this->assertStringContainsString('skeleton-card-footer', $output);
        $this->assertStringContainsString('skeleton-avatar', $output);
    }

    public function test_render_skeleton_chart_renders_bars(): void
    {
        $output = render_skeleton('chart', [
            'bars' => 5,
            'height' => 180
        ]);

        $this->assertStringContainsString('skeleton-chart-container', $output);
        $this->assertStringContainsString('height: 180px;', $output);
        $this->assertSame(5, substr_count($output, 'skeleton-chart-bar-wrap'));
    }

    public function test_render_skeleton_list_renders_items(): void
    {
        $output = render_skeleton('list', [
            'count' => 6,
            'hasAvatar' => true,
            'hasBadge' => true
        ]);

        $this->assertStringContainsString('skeleton-list', $output);
        $this->assertSame(6, substr_count($output, 'skeleton-list-item'));
        $this->assertSame(6, substr_count($output, 'skeleton-avatar'));
        $this->assertSame(6, substr_count($output, 'skeleton-badge'));
    }

    public function test_render_skeleton_profile_renders_profile_structure(): void
    {
        $output = render_skeleton('profile', ['fields' => 8]);

        $this->assertStringContainsString('skeleton-profile-header', $output);
        $this->assertStringContainsString('skeleton-avatar-xl', $output);
        $this->assertStringContainsString('skeleton-profile-grid', $output);
    }

    public function test_render_skeleton_form_renders_fields_and_inputs(): void
    {
        $output = render_skeleton('form', [
            'fields' => 3,
            'columns' => 2,
            'hasButton' => true
        ]);

        $this->assertStringContainsString('skeleton-form-group', $output);
        $this->assertSame(3, substr_count($output, 'skeleton-input'));
        $this->assertStringContainsString('grid-template-columns: repeat(2, 1fr)', $output);
    }

    public function test_all_skeleton_partials_contain_aria_hidden(): void
    {
        $components = ['stat_card', 'table', 'card', 'chart', 'list', 'profile', 'form'];
        foreach ($components as $component) {
            $output = render_skeleton($component);
            $this->assertStringContainsString('aria-hidden="true"', $output, "Component {$component} must have aria-hidden on decorative elements");
        }
    }
}

