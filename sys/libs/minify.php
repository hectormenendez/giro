<?php
/**
 * Minify
 * @author Stephen Clay <steve@mrclay.org>
 * @author http://code.google.com/u/1stvamp/ (Issue 64 patch)
 *
 * @note extracted bits I needed and adapted them for the framework.
 */
class Minify {

	private static $inhack = false;

    /**
     * Minify Javascript
     *
     * @param string $js Javascript to be minified
     * @return string
     */
    public static function js($js){
        # look out for syntax like "++ +" and "- ++"
        $p = '\\+';
        $m = '\\-';
        if (preg_match("/([$p$m])(?:\\1 [$p$m]| (?:$p$p|$m$m))/", $js)) {
            # likely pre-minified and would be broken by JSMin
            return $js;
        }
        $jsmin = new JSMin($js);
        return $jsmin->min();
    }


    public static function css($css){
        $css = str_replace("\r\n", "\n", $css);
        # preserve empty comment after '>'
        # http://www.webdevout.net/css-hacks#in_css-selectors
        $css = preg_replace('@>/\\*\\s*\\*/@', '>/*keep*/', $css);
        # preserve empty comment between property and value
        # http://css-discuss.incutio.com/?page=BoxModelHack
        $css = preg_replace('@/\\*\\s*\\*/\\s*:@', '/*keep*/:', $css);
        $css = preg_replace('@:\\s*/\\*\\s*\\*/@', ':/*keep*/', $css);
        # apply callback to all valid comments (and strip out surrounding ws
        $css = preg_replace_callback(
	        '@\\s*/\\*([\\s\\S]*?)\\*/\\s*@',
	        'self::css_comments', $css
        );
        # remove ws around { } and last semicolon in declaration block
        $css = preg_replace('/\\s*{\\s*/', '{', $css);
        $css = preg_replace('/;?\\s*}\\s*/', '}', $css);    
        # remove ws surrounding semicolons
        $css = preg_replace('/\\s*;\\s*/', ';', $css);
        # remove ws around urls
        $css = preg_replace('/
                url\\(      # url(
                \\s*
                ([^\\)]+?)  # 1 = the URL (really just a bunch of non right parenthesis)
                \\s*
                \\)         # )
            /x', 'url($1)', $css);
        # remove ws between rules and colons
        $css = preg_replace('/
                \\s*
                ([{;])              # 1 = beginning of block or rule separator 
                \\s*
                ([\\*_]?[\\w\\-]+)  # 2 = property (and maybe IE filter)
                \\s*
                :
                \\s*
                (\\b|[#\'"-])        # 3 = first character of a value
            /x', '$1$2:$3', $css);
        # remove ws in selectors
        $css = preg_replace_callback('/
                (?:              # non-capture
                    \\s*
                    [^~>+,\\s]+  # selector part
                    \\s*
                    [,>+~]       # combinators
                )+
                \\s*
                [^~>+,\\s]+      # selector part
                {                # open declaration block
            /x'
            ,'self::css_selectors', $css);
        # minimize hex colors
        $css = preg_replace('/([^=])#([a-f\\d])\\2([a-f\\d])\\3([a-f\\d])\\4([\\s;\\}])/i'
            , '$1#$2$3$4$5', $css);
        # remove spaces between font families
        $css = preg_replace_callback('/font-family:([^;}]+)([;}])/'
            ,'self::css_fontfamily', $css);
        $css = preg_replace('/@import\\s+url/', '@import url', $css);
        # replace any ws involving newlines with a single newline
        $css = preg_replace('/[ \\t]*\\n+\\s*/', "\n", $css);
        # separate common descendent selectors w/ newlines (to limit line lengths)
        $css = preg_replace('/([\\w#\\.\\*]+)\\s+([\\w#\\.\\*]+){/', "$1\n$2{", $css);
        # Use newline after 1st numeric value (to limit line lengths).
        $css = preg_replace('/
            ((?:padding|margin|border|outline):\\d+(?:px|em)?) # 1 = prop : 1st numeric value
            \\s+
            /x'
            ,"$1\n", $css);
        # prevent triggering IE6 bug: http://www.crankygeek.com/ie6pebug/
        $css = preg_replace('/:first-l(etter|ine)\\{/', ':first-l$1 {', $css);
        self::$inhack = false;
        return trim($css);
    }

    /**
     * Process a comment and return a replacement
     * 
     * @param array $m regex matches
     * @return string
     */
    private static function css_comments($m){
        $hasSurroundingWs = (trim($m[0]) !== $m[1]);
        $m = $m[1]; 
        # $m is the comment content w/o the surrounding tokens, 
        # but the return value will replace the entire comment.
        if ($m==='keep') return '/**/';
        # component of http://tantek.com/CSS/Examples/midpass.html
        if ($m=== '" "') return '/*" "*/';
        # component of http://tantek.com/CSS/Examples/midpass.html
        if (preg_match('@";\\}\\s*\\}/\\*\\s+@', $m))
        	return '/*";}}/* */';
        if (self::$inhack) {
            # inversion: feeding only to one browser
            if (preg_match('@
                    ^/               # comment started like /*/
                    \\s*
                    (\\S[\\s\\S]+?)  # has at least some non-ws content
                    \\s*
                    /\\*             # ends like /*/ or /**/
                @x', $m, $n)) {
                # end hack mode after this comment, but preserve the hack and comment content
                self::$inhack = false;
                return "/*/{$n[1]}/**/";
            }
        }
        # comment ends like \*/
        if (substr($m, -1) === '\\') {
            # begin hack mode and preserve hack
            self::$inhack = true;
            return '/*\\*/';
        }
        # comment looks like /*/ foo */
        if ($m !== '' && $m[0] === '/') { 
            # begin hack mode and preserve hack
            self::$inhack = true;
            return '/*/*/';
        }
        if (self::$inhack) {
            # a regular comment ends hack mode but should be preserved
            self::$inhack = false;
            return '/**/';
        }
        # Issue 107: if there's any surrounding whitespace, it may be important, so 
        # replace the comment with a single space
        ## remove all other comments
        return $hasSurroundingWs? ' ': '';
    }

    /**
     * Replace what looks like a set of selectors  
     *
     * @param array $m regex matches
     * @return string
     */
    private static function css_selectors($m)  {
        return preg_replace('/\\s*([,>+~])\\s*/', '$1', $m[0]);
    }
    
    
    /**
     * Process a font-family listing and return a replacement
     * 
     * @param array $m regex matches
     * @return string   
     */
    private static function css_fontfamily($m){
        $m[1] = preg_replace('/
                \\s*
                (
                    "[^"]+"      # 1 = family in double qutoes
                    |\'[^\']+\'  # or 1 = family in single quotes
                    |[\\w\\-]+   # or 1 = unquoted family
                )
                \\s*
            /x', '$1', $m[1]);
        return 'font-family:' . $m[1] . $m[2];
    }

}

/**
 * jsmin.php - extended PHP implementation of Douglas Crockford's JSMin.
 *
 * <code>
 * $minifiedJs = JSMin::minify($js);
 * </code>
 *
 * This is a direct port of jsmin.c to PHP with a few PHP performance tweaks and
 * modifications to preserve some comments (see below). Also, rather than using
 * stdin/stdout, JSMin::minify() accepts a string as input and returns another
 * string as output.
 * 
 * Comments containing IE conditional compilation are preserved, as are multi-line
 * comments that begin with "/*!" (for documentation purposes). In the latter case
 * newlines are inserted around the comment to enhance readability.
 *
 * PHP 5 or higher is required.
 *
 * Permission is hereby granted to use this version of the library under the
 * same terms as jsmin.c, which has the following license:
 *
 * --
 * Copyright (c) 2002 Douglas Crockford  (www.crockford.com)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * The Software shall be used for Good, not Evil.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * --
 *
 * @package JSMin
 * @author Ryan Grove <ryan@wonko.com> (PHP port)
 * @author Steve Clay <steve@mrclay.org> (modifications + cleanup)
 * @author Andrea Giammarchi <http://www.3site.eu> (spaceBeforeRegExp)
 * @copyright 2002 Douglas Crockford <douglas@crockford.com> (jsmin.c)
 * @copyright 2008 Ryan Grove <ryan@wonko.com> (PHP port)
 * @license http://opensource.org/licenses/mit-license.php MIT License
 * @link http://code.google.com/p/jsmin-php/
 */

class JSMin {
    const ORD_LF            = 10;
    const ORD_SPACE         = 32;
    const ACTION_KEEP_A     = 1;
    const ACTION_DELETE_A   = 2;
    const ACTION_DELETE_A_B = 3;
    
    private $a           = "\n";
    private $b           = '';
    private $input       = '';
    private $inputIndex  = 0;
    private $inputLength = 0;
    private $lookAhead   = null;
    private $output      = '';

    /*
     * Don't create a JSMin instance, instead use the static function minify,
     * which checks for mb_string function overloading and avoids errors
     * trying to re-minify the output of Closure Compiler
     *
     * @private
     */
    public function __construct($input){
        $this->input = $input;
    }
    
    /**
     * Perform minification, return result
     */
    public function min(){
        if ($this->output !== '') { // min already run
            return $this->output;
        }
        $mbIntEnc = null;
        if (function_exists('mb_strlen') && ((int)ini_get('mbstring.func_overload') & 2)) {
            $mbIntEnc = mb_internal_encoding();
            mb_internal_encoding('8bit');
        }
        $this->input = str_replace("\r\n", "\n", $this->input);
        $this->inputLength = strlen($this->input);

        $this->action(self::ACTION_DELETE_A_B);
        
        while ($this->a !== null) {
            // determine next command
            $command = self::ACTION_KEEP_A; // default
            if ($this->a === ' ') {
                if (! $this->isAlphaNum($this->b)) {
                    $command = self::ACTION_DELETE_A;
                }
            } elseif ($this->a === "\n") {
                if ($this->b === ' ') {
                    $command = self::ACTION_DELETE_A_B;
                // in case of mbstring.func_overload & 2, must check for null b,
                // otherwise mb_strpos will give WARNING
                } elseif ($this->b === null
                          || (false === strpos('{[(+-', $this->b)
                              && ! $this->isAlphaNum($this->b))) {
                    $command = self::ACTION_DELETE_A;
                }
            } elseif (! $this->isAlphaNum($this->a)) {
                if ($this->b === ' '
                    || ($this->b === "\n" 
                        && (false === strpos('}])+-"\'', $this->a)))) {
                    $command = self::ACTION_DELETE_A_B;
                }
            }
            $this->action($command);
        }
        $this->output = trim($this->output);

        if ($mbIntEnc !== null) {
            mb_internal_encoding($mbIntEnc);
        }
        return $this->output;
    }
    
    /**
     * ACTION_KEEP_A = Output A. Copy B to A. Get the next B.
     * ACTION_DELETE_A = Copy B to A. Get the next B.
     * ACTION_DELETE_A_B = Get the next B.
     */
    private function action($command){
        switch ($command) {
            case self::ACTION_KEEP_A:
                $this->output .= $this->a;
                // fallthrough
            case self::ACTION_DELETE_A:
                $this->a = $this->b;
                if ($this->a === "'" || $this->a === '"') { // string literal
                    $str = $this->a; // in case needed for exception
                    while (true) {
                        $this->output .= $this->a;
                        $this->a       = $this->get();
                        if ($this->a === $this->b) { // end quote
                            break;
                        }
                        if (ord($this->a) <= self::ORD_LF) {
                            throw new JSMin_UnterminatedStringException(
                                "JSMin: Unterminated String at byte "
                                . $this->inputIndex . ": {$str}");
                        }
                        $str .= $this->a;
                        if ($this->a === '\\') {
                            $this->output .= $this->a;
                            $this->a       = $this->get();
                            $str .= $this->a;
                        }
                    }
                }
                // fallthrough
            case self::ACTION_DELETE_A_B:
                $this->b = $this->next();
                if ($this->b === '/' && $this->isRegexpLiteral()) { // RegExp literal
                    $this->output .= $this->a . $this->b;
                    $pattern = '/'; // in case needed for exception
                    while (true) {
                        $this->a = $this->get();
                        $pattern .= $this->a;
                        if ($this->a === '/') { // end pattern
                            break; // while (true)
                        } elseif ($this->a === '\\') {
                            $this->output .= $this->a;
                            $this->a       = $this->get();
                            $pattern      .= $this->a;
                        } elseif (ord($this->a) <= self::ORD_LF) {
                            throw new JSMin_UnterminatedRegExpException(
                                "JSMin: Unterminated RegExp at byte "
                                . $this->inputIndex .": {$pattern}");
                        }
                        $this->output .= $this->a;
                    }
                    $this->b = $this->next();
                }
            // end case ACTION_DELETE_A_B
        }
    }
    
    private function isRegexpLiteral(){
        if (false !== strpos("\n{;(,=:[!&|?", $this->a)) { // we aren't dividing
            return true;
        }
        if (' ' === $this->a) {
            $length = strlen($this->output);
            if ($length < 2) { // weird edge case
                return true;
            }
            // you can't divide a keyword
            if (preg_match('/(?:case|else|in|return|typeof)$/', $this->output, $m)) {
                if ($this->output === $m[0]) { // odd but could happen
                    return true;
                }
                // make sure it's a keyword, not end of an identifier
                $charBeforeKeyword = substr($this->output, $length - strlen($m[0]) - 1, 1);
                if (! $this->isAlphaNum($charBeforeKeyword)) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Get next char. Convert ctrl char to space.
     */
    private function get(){
        $c = $this->lookAhead;
        $this->lookAhead = null;
        if ($c === null) {
            if ($this->inputIndex < $this->inputLength) {
                $c = $this->input[$this->inputIndex];
                $this->inputIndex += 1;
            } else {
                return null;
            }
        }
        if ($c === "\r" || $c === "\n") {
            return "\n";
        }
        if (ord($c) < self::ORD_SPACE) { // control char
            return ' ';
        }
        return $c;
    }
    
    /**
     * Get next char. If is ctrl character, translate to a space or newline.
     */
    private function peek(){
        $this->lookAhead = $this->get();
        return $this->lookAhead;
    }
    
    /**
     * Is $c a letter, digit, underscore, dollar sign, escape, or non-ASCII?
     */
    private function isAlphaNum($c)
    {
        return (preg_match('/^[0-9a-zA-Z_\\$\\\\]$/', $c) || ord($c) > 126);
    }
    
    private function singleLineComment(){
        $comment = '';
        while (true) {
            $get = $this->get();
            $comment .= $get;
            if (ord($get) <= self::ORD_LF) { // EOL reached
                // if IE conditional comment
                if (preg_match('/^\\/@(?:cc_on|if|elif|else|end)\\b/', $comment)) {
                    return "/{$comment}";
                }
                return $get;
            }
        }
    }
    
    private function multipleLineComment(){
        $this->get();
        $comment = '';
        while (true) {
            $get = $this->get();
            if ($get === '*') {
                if ($this->peek() === '/') { // end of comment reached
                    $this->get();
                    // if comment preserved by YUI Compressor
                    if (0 === strpos($comment, '!')) {
                        return "\n/*" . substr($comment, 1) . "*/\n";
                    }
                    // if IE conditional comment
                    if (preg_match('/^@(?:cc_on|if|elif|else|end)\\b/', $comment)) {
                        return "/*{$comment}*/";
                    }
                    return ' ';
                }
            } elseif ($get === null) {
                throw new JSMin_UnterminatedCommentException(
                    "JSMin: Unterminated comment at byte "
                    . $this->inputIndex . ": /*{$comment}");
            }
            $comment .= $get;
        }
    }
    
    /**
     * Get the next character, skipping over comments.
     * Some comments may be preserved.
     */
    private function next(){
        $get = $this->get();
        if ($get !== '/') {
            return $get;
        }
        switch ($this->peek()) {
            case '/': return $this->singleLineComment();
            case '*': return $this->multipleLineComment();
            default: return $get;
        }
    }
}

class JSMin_UnterminatedStringException  extends Exception {}
class JSMin_UnterminatedCommentException extends Exception {}
class JSMin_UnterminatedRegExpException  extends Exception {}