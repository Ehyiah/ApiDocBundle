<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration;

use Symfony\Component\Tui\Style\Align;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Style\TextAlign;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\SettingsListWidget;
use Symfony\Component\Tui\Widget\TextWidget;

final class TuiUi
{
    public const ACCENT = 'cyan';

    public static function applyTheme(Tui $tui): void
    {
        $tui->addStyleSheet(new StyleSheet([
            SelectListWidget::class . '::selected' => new Style(bold: true, color: self::ACCENT),
            SelectListWidget::class . '::selected:focus' => new Style(bold: true, color: self::ACCENT),
            SettingsListWidget::class . '::value-selected' => new Style(color: self::ACCENT),
            SettingsListWidget::class . '::value-selected:focus' => new Style(color: self::ACCENT, bold: true),
            SettingsListWidget::class . '::label-selected:focus' => new Style(bold: true, color: self::ACCENT),
        ]));
    }

    public static function banner(): ContainerWidget
    {
        $container = new ContainerWidget();

        $title = new TextWidget('ApiDocBundle');
        $title->setStyle(new Style(font: 'slant', color: self::ACCENT, bold: true, align: Align::Center));
        $container->add($title);

        $subtitle = new TextWidget('OpenAPI Component Generator');
        $subtitle->setStyle(new Style(color: 'gray', textAlign: TextAlign::Center));
        $container->add($subtitle);

        return $container;
    }

    public static function header(string $title, ?string $subtitle = null): ContainerWidget
    {
        $panel = new ContainerWidget();
        $panel->setStyle(new Style(
            border: new Border(1, 2, 1, 2, BorderPattern::ROUNDED, self::ACCENT),
            padding: Padding::from([0, 1]),
        ));

        $titleWidget = new TextWidget($title);
        $titleWidget->setStyle(new Style(color: self::ACCENT, bold: true));
        $panel->add($titleWidget);

        if (null !== $subtitle && '' !== $subtitle) {
            $subtitleWidget = new TextWidget($subtitle);
            $subtitleWidget->setStyle(new Style(color: 'gray'));
            $panel->add($subtitleWidget);
        }

        return $panel;
    }

    public static function hints(string $text): TextWidget
    {
        $widget = new TextWidget($text);
        $widget->setStyle(new Style(color: 'gray'));

        return $widget;
    }
}
