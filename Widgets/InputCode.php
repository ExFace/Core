<?php

namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\CodeFormatter;

/**
 * A code input that can show and format code inside the Ace editor.
 * 
 * ##Examples:
 * 
 * ```
 *  {
 *      "widget_type": "InputCode",
 *      "language": "sql",
 *      "disable": true,
 *      "width": "100%",
 *      "height": "100%",
 *      "value": "SELECT * FROM myTable",
 *      "code_formatter": {
 *          "prettify": true,
 *          "colorize": true,
 *          "language": "sql",
 *          "dialect": "tsql"
 *       }
 *  }
 * ```
 * 
 * @author Sergej Riel
 */
class InputCode extends Input
{
    const LANGUAGE_SQL = 'sql';
    
    private $wrapLines = true;
    
    private $language = null;
    private $codeFormatter = null;
    private ?UxonObject $codeFormatterUxon = null;
    
    
    /**
     * @param string|null $default
     * @return string|null
     */
    public function getLanguage(string $default = null) : ?string
    {
        return $this->language ?? $default;
    }

    /**
     * Sets the code language for the syntax highlighting: e.g. javascript, sql, etc.
     * 
     * More languages are supported than are listed here.
     * 
     * @uxon-property language
     * @uxon-type [abap,abc,actionscript,ada,alda,apache_conf,apex,applescript,aql,asciidoc,asl,assembly_arm32,assembly_x86,astro,autohotkey,basic,batchfile,bibtex,c_cpp,c9search,cirru,clojure,cobol,coffee,coldfusion,crystal,csharp,csound_document,csound_orchestra,csound_score,csp,css,curly,cuttlefish,d,dart,diff,django,dockerfile,dot,drools,edifact,eiffel,ejs,elixir,elm,erlang,flix,forth,fortran,fsharp,fsl,ftl,gcode,gherkin,gitignore,glsl,gobstones,golang,graphqlschema,groovy,haml,handlebars,haskell,haskell_cabal,haxe,hjson,html,html_elixir,html_ruby,ini,io,ion,jack,jade,java,javascript,jexl,json,json5,jsoniq,jsp,jssm,jsx,julia,kotlin,latex,latte,less,liquid,lisp,livescript,logiql,logtalk,lsl,lua,luapage,lucene,makefile,markdown,mask,matlab,maze,mediawiki,mel,mips,mixal,mushcode,mysql,nasal,nginx,nim,nix,nsis,nunjucks,objectivec,ocaml,odin,partiql,pascal,perl,pgsql,php,php_laravel_blade,pig,plain_text,plsql,powershell,praat,prisma,prolog,properties,protobuf,prql,puppet,python,qml,r,raku,razor,rdoc,red,redshift,rhtml,robot,rst,ruby,rust,sac,sass,scad,scala,scheme,scrypt,scss,sh,sjs,slim,smarty,smithy,snippets,soy_template,space,sparql,sql,sqlserver,stylus,svg,swift,tcl,terraform,tex,text,textile,toml,tsx,turtle,twig,typescript,vala,vbscript,velocity,verilog,vhdl,visualforce,vue,wollok,xml,xquery,yaml,zeek,zig]
     * @uxon-default text
     * 
     * @param string $value
     * @return $this
     */
    public function setLanguage(string $value) : InputCode
    {
        $this->language = $value;
        return $this;
    }
    
    /**
     * It gets the code formatter.
     * 
     * @return CodeFormatter
     */
    public function getCodeFormatter(): CodeFormatter
    {
        if (!$this->hasCodeFormatter()) {
            if ($this->codeFormatterUxon === null) {
                $this->codeFormatter = $this->createDefaultCodeFormatter($this->language);
            } else {
                $this->codeFormatter = new CodeFormatter($this, $this->codeFormatterUxon);
            }
        }
        return $this->codeFormatter;
    }

    /**
     * The code formatter can format different code languages.
     * 
     * Use the 'dialect' property if the language has multiple dialects, 
     * such as 'SQL', which includes for example T-SQL and MySQL.
     * 
     * @uxon-property code_formatter
     * @uxon-type \exface\Core\Widgets\Parts\CodeFormatter
     * @uxon-template {"language": "sql", "dialect": "tsql"}
     * 
     * @param UxonObject $uxon
     * @return $this
     */
    protected function setCodeFormatter(UxonObject $uxon) : InputCode
    {
        $this->codeFormatterUxon = $uxon;
        $this->codeFormatter = null;
        return $this;
    }

    /**
     * @return bool
     */
    protected function hasCodeFormatter() : bool
    {
        return $this->codeFormatter !== null;
    }

    /**
     * Creates default code formatter
     * 
     * @param $language
     * @return CodeFormatter
     */
    protected function createDefaultCodeFormatter($language) : CodeFormatter
    {
        return match ($language) {
            self::LANGUAGE_SQL => $this->createSqlCodeFormatter(),
            default => new CodeFormatter($this, new UxonObject([
                'language' => $language,
            ])),
        };
    }

    /**
     * Creates default sql formatter.
     * 
     * @return CodeFormatter
     */
    protected function createSqlCodeFormatter() : CodeFormatter
    {
        return new CodeFormatter($this, new UxonObject([
            'language' => 'sql',
            'dialect' => 'sql',
        ]));
    }

    /**
     * @return bool
     */
    public function hasWrapLines() : bool
    {
        return $this->wrapLines;
    }

    /**
     * Set to FALSE to prevent line wrapping
     * 
     * @uxon-property wrap_lines
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $trueOrFalse
     * @return $this
     */
    public function setWrapLines(bool $trueOrFalse) : InputCode
    {
        $this->wrapLines = $trueOrFalse;
        return $this;
    }
}