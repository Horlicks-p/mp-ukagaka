<?php

use PHPUnit\Framework\TestCase;

final class TemplateRenderTest extends TestCase {
    public function test_render_prompt_template_replaces_known_variables(): void {
        $template = 'Hello {{name}}, today is {{day}}.';

        $this->assertSame(
            'Hello Frieren, today is Monday.',
            mpu_render_prompt_template($template, ['name' => 'Frieren', 'day' => 'Monday'])
        );
    }

    public function test_render_prompt_template_removes_unknown_variables(): void {
        $this->assertSame('Hello .', mpu_render_prompt_template('Hello {{missing}}.', ['known' => 'value']));
    }

    public function test_render_prompt_template_leaves_invalid_input_unchanged(): void {
        $this->assertSame('', mpu_render_prompt_template('', ['name' => 'Frieren']));
    }
}
