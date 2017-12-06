<?php
namespace YoastSeoForTypo3\YoastSeo\ViewHelpers;
/*
 * This file belongs to the package "YOAST".
 * And was backported from the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */
use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;
/**
 * Space Removal ViewHelper
 *
 * Removes redundant spaces between HTML tags while
 * preserving the whitespace that may be inside HTML
 * tags. Trims the final result before output.
 *
 * Heavily inspired by Twig's corresponding node type.
 *
 * <code title="Usage of f:spaceless">
 * <f:spaceless>
 * <div>
 *     <div>
 *         <div>text
 *
 * text</div>
 *     </div>
 * </div>
 * </code>
 * <output>
 * <div><div><div>text
 *
 * text</div></div></div>
 * </output>
 */
class SpacelessViewHelper extends AbstractViewHelper
{
    /**
     * @var boolean
     */
    protected $escapeOutput = false;
    /**
     * @param null $content
     * @return mixed|string
     */
    public function render($content = null)
    {
        if ($content === null) {
            $content = $this->renderChildren();
        }
        return trim(preg_replace('/\\>\\s+\\</', '><', $content));
    }
}