<?php
namespace exface\Core\DataTypes;

/**
 * Base data type for all sorts of code: HTML, JSON, SQL, JavaScript, etc.
 * 
 * @author Andrej Kabachnik
 */
class CodeDataType extends TextDataType
{
    private ?string $language = null;
    
    public function getLanguage(): ?string
    {
        return $this->language;
    }

    /**
     * Specifies a specific programming language
     * 
     * Technically, the concrete implementation of a code editor used in the facade determines, how exactly the code is
     * formatted and highlighted. So in reality more, less or other languages might be supported. Consequently,
     * language names are must be explicitly supported by the editor library used in the facade.
     * 
     * By default, languages supported by the popular ACE editor are part of the autosuggest for this property.
     * 
     * Github provides a list of common programming languages with mappings to the specific operating modes of popular
     * JS editor libraries: https://github.com/github-linguist/linguist/blob/main/lib/linguist/languages.yml.
     * 
     * @uxon-property language
     * @uxon-type [abap,abc,actionscript,ada,alda,apache_conf,apex,applescript,aql,asciidoc,asl,assembly_arm32,assembly_x86,astro,autohotkey,basic,batchfile,bibtex,c_cpp,c9search,cirru,clojure,cobol,coffee,coldfusion,crystal,csharp,csound_document,csound_orchestra,csound_score,csp,css,curly,cuttlefish,d,dart,diff,django,dockerfile,dot,drools,edifact,eiffel,ejs,elixir,elm,erlang,flix,forth,fortran,fsharp,fsl,ftl,gcode,gherkin,gitignore,glsl,gobstones,golang,graphqlschema,groovy,haml,handlebars,haskell,haskell_cabal,haxe,hjson,html,html_elixir,html_ruby,ini,io,ion,jack,jade,java,javascript,jexl,json,json5,jsoniq,jsp,jssm,jsx,julia,kotlin,latex,latte,less,liquid,lisp,livescript,logiql,logtalk,lsl,lua,luapage,lucene,makefile,markdown,mask,matlab,maze,mediawiki,mel,mips,mixal,mushcode,mysql,nasal,nginx,nim,nix,nsis,nunjucks,objectivec,ocaml,odin,partiql,pascal,perl,pgsql,php,php_laravel_blade,pig,plain_text,plsql,powershell,praat,prisma,prolog,properties,protobuf,prql,puppet,python,qml,r,raku,razor,rdoc,red,redshift,rhtml,robot,rst,ruby,rust,sac,sass,scad,scala,scheme,scrypt,scss,sh,sjs,slim,smarty,smithy,snippets,soy_template,space,sparql,sql,sqlserver,stylus,svg,swift,tcl,terraform,tex,text,textile,toml,tsx,turtle,twig,typescript,vala,vbscript,velocity,verilog,vhdl,visualforce,vue,wollok,xml,xquery,yaml,zeek,zig]
     * @uxon-default text
     * 
     * @uxon-property language
     * @uxon-type string
     * 
     * @param string|null $language
     * @return $this
     */
    protected function setLanguage(?string $language): CodeDataType
    {
        $this->language = $language;
        return $this;
    }
}