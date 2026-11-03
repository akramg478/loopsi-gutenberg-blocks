<?php
/**
 * Dynamic block render template.
 *
 * @package Loopsi_Blocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    Block content.
 * @var WP_Block             $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

return Loopsi\Core\BlockRenderer::render( $attributes, $content, $block );
