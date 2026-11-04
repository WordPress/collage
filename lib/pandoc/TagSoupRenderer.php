<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class TagSoupRenderer
{
    /**
     * @param list<TagSoupTag> $tokens
     */
    public function render(array $tokens): string
    {
        $html = '';
        $count = count($tokens);
        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];
            if (!$token instanceof TagSoupTag) {
                continue;
            }

            if (
                $token->type === TagSoupTag::OPEN
                && $this->minimizeTag($token->name)
                && isset($tokens[$i + 1])
                && $tokens[$i + 1] instanceof TagSoupTag
                && $tokens[$i + 1]->type === TagSoupTag::CLOSE
                && $tokens[$i + 1]->name === $token->name
            ) {
                $html .= $this->renderOpen($token, ' /');
                ++$i;
                continue;
            }

            if ($token->type === TagSoupTag::OPEN && $this->rawTag($token->name)) {
                $html .= $this->renderOpen($token, '');
                ++$i;
                while ($i < $count) {
                    $inner = $tokens[$i];
                    if ($inner instanceof TagSoupTag && $inner->type === TagSoupTag::CLOSE && $inner->name === $token->name) {
                        $html .= $this->renderToken($inner);
                        break;
                    }
                    if ($inner instanceof TagSoupTag && $inner->type === TagSoupTag::TEXT) {
                        $html .= $inner->text;
                    } elseif ($inner instanceof TagSoupTag) {
                        $html .= $this->renderToken($inner);
                    }
                    ++$i;
                }
                continue;
            }

            $html .= $this->renderToken($token);
        }

        return $html;
    }

    private function renderToken(TagSoupTag $token): string
    {
        return match ($token->type) {
            TagSoupTag::OPEN => $this->renderOpen($token, str_starts_with($token->name, '?') ? ' ?' : ''),
            TagSoupTag::CLOSE => '</' . $token->name . '>',
            TagSoupTag::TEXT => TagSoupEntity::escapeXml($token->text),
            TagSoupTag::COMMENT => '<!--' . $this->escapeComment($token->text) . '-->',
            default => '',
        };
    }

    private function renderOpen(TagSoupTag $token, string $shut): string
    {
        $html = '<' . $token->name;
        foreach ($token->attributes as $attribute) {
            $name = $attribute['name'];
            $value = $attribute['value'];
            if ($name === '' && $value === '') {
                $html .= ' ""';
            } elseif ($value === '') {
                $html .= ' ' . $name;
            } elseif ($name === '') {
                $html .= ' "' . TagSoupEntity::escapeXml($value) . '"';
            } else {
                $html .= ' ' . $name . '="' . TagSoupEntity::escapeXml($value) . '"';
            }
        }

        return $html . $shut . '>';
    }

    private function minimizeTag(string $name): bool
    {
        return $name === 'br';
    }

    private function rawTag(string $name): bool
    {
        return in_array($name, ['script', 'style', 'textarea', 'xmp'], true);
    }

    private function escapeComment(string $comment): string
    {
        return str_replace('-->', '-- >', $comment);
    }
}
