<?php

namespace Sitecake\DOM;


trait HTMLContentAwareTrait
{
    /**
     * HTML source
     *
     * @var string
     */
    protected $source;

    /**
     * Loads passed HTML source
     *
     * @param string $html
     */
    public function loadHTML($html) {
        $this->source = trim($html);
    }

    /**
     * Returns HTML source
     *
     * @return string
     */
    public function getSource() {
        return trim($this->source);
    }

    /**
     * @param $content
     *
     * @return string
     */
    protected function evaluate($content)
    {
        ob_start();
        eval('?>' . $content);
        $result = ob_get_contents();
        ob_end_clean();

        return $result;
    }
}