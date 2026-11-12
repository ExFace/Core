;(function (global, factory) {
  typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory() :
      typeof define === 'function' && define.amd ? define(factory()) :
          global.exfColorTools = factory()
}(this, (function () { 'use strict';
  var exfColorTools = {

    // css-color -> {r,g,b,a} via offscreen element + getComputedStyle
    _cssColorToRgba: function(color) {
      if (!color) return null;
      const el = document.createElement('span');
      el.style.color = color;
      // the element must be in the DOM for getComputedStyle to be reliable
      document.body.appendChild(el);
      const cs = getComputedStyle(el).color; // "rgb(r, g, b)" or "rgba(r, g, b, a)"
      document.body.removeChild(el);

      const m = cs.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([.\d]+))?\)/i);
      if (!m) return null;
      const [, r, g, b, a] = m;
      return { r: +r, g: +g, b: +b, a: a !== undefined ? +a : 1 };
    },
    
    _rgbaToHex: function({r,g,b,a=1}) {
      const h = x => x.toString(16).padStart(2,'0');
      // We ignore a in hex; however, CSS variables can also be assigned rgba().
      return `#${h(r)}${h(g)}${h(b)}`;
    },

    _rgbToHsl: function({r,g,b}) {
      r/=255; g/=255; b/=255;
      const max=Math.max(r,g,b), min=Math.min(r,g,b);
      let h=0, s=0, l=(max+min)/2;
      if (max !== min) {
        const d = max-min;
        s = l>0.5 ? d/(2-max-min) : d/(max+min);
        switch (max) {
          case r: h=(g-b)/d + (g<b?6:0); break;
          case g: h=(b-r)/d + 2; break;
          case b: h=(r-g)/d + 4; break;
        }
        h/=6;
      }
      return { h, s, l };
    },

    _hslToRgb: function({h,s,l}) {
      let r,g,b;
      if (s === 0) { r=g=b=l; }
      else {
        const q = l < .5 ? l*(1+s) : l + s - l*s;
        const p = 2*l - q;
        const hue = t=>{
          if (t<0) t+=1;
          if (t>1) t-=1;
          if (t<1/6) return p + (q-p)*6*t;
          if (t<1/2) return q;
          if (t<2/3) return p + (q-p)*(2/3 - t)*6;
          return p;
        };
        r = hue(h+1/3); g = hue(h); b = hue(h-1/3);
      }
      return { r:Math.round(r*255), g:Math.round(g*255), b:Math.round(b*255) };
    },

    /**
     * Returns the opaque theme background as {r,g,b,a}, falling back to white.
     * Reads the UI5 theming CSS variable, so dark themes are respected automatically.
     *
     * @returns {{r:number,g:number,b:number,a:number}}
     */
    _getDefaultBackdropRgba: function() {
      const white = { r: 255, g: 255, b: 255, a: 1 };
      if (typeof document === 'undefined' || !document.documentElement) return white;
      const sThemeBg = getComputedStyle(document.documentElement).getPropertyValue('--sapBackgroundColor').trim();
      const rgba = sThemeBg ? this._cssColorToRgba(sThemeBg) : null;
      return (rgba && rgba.a >= 1) ? rgba : white;
    },

    /**
     * Alpha-blends a (semi-)transparent color over an opaque backdrop.
     * Invalid or non-opaque backdrops are replaced by the theme default.
     *
     * @param {{r:number,g:number,b:number,a:number}} rgba
     * @param {string} [backdropCssColor]
     * @returns {{r:number,g:number,b:number,a:number}}
     */
    _blendOverBackdrop: function(rgba, backdropCssColor) {
      let backdrop = backdropCssColor ? this._cssColorToRgba(backdropCssColor) : null;
      if (!backdrop) {
        backdrop = this._getDefaultBackdropRgba();
      }
      
      // Ensure that the backdrop is treated as opaque for blending purposes.
      backdrop.a = 1.0;
      
      // Source-over compositing on an opaque backdrop is a lerp weighted by the source alpha
      return this.lerpRgba(backdrop, Object.assign({}, rgba, { a: 1 }), rgba.a);
    },

    /**
     * Linearly interpolates between two {r,g,b,a} colors, channel by channel (including alpha).
     * `t = 0` returns `from`, `t = 1` returns `to`; values outside [0..1] are clamped.
     * Interpolation happens in sRGB space, which matches how browsers composite colors.
     *
     * @param {{r:number,g:number,b:number,a?:number}} from
     * @param {{r:number,g:number,b:number,a?:number}} to
     * @param {number} t
     * @returns {{r:number,g:number,b:number,a:number}}
     */
    lerpRgba: function(from, to, t) {
      t = Math.max(0, Math.min(1, t));
      const lerp = (x, y) => x + (y - x) * t;
      const aFrom = from.a === undefined ? 1 : from.a;
      const aTo = to.a === undefined ? 1 : to.a;
      return {
        r: Math.round(lerp(from.r, to.r)),
        g: Math.round(lerp(from.g, to.g)),
        b: Math.round(lerp(from.b, to.b)),
        a: lerp(aFrom, aTo)
      };
    },

    // WCAG-Helpers
    _srgbToLinear: function(c01) {
      // c01: 0..1
      return (c01 <= 0.03928) ? (c01 / 12.92) : Math.pow((c01 + 0.055) / 1.055, 2.4);
    },
    
    _relativeLuminance: function({r, g, b}) {
      const R = this._srgbToLinear(r / 255);
      const G = this._srgbToLinear(g / 255);
      const B = this._srgbToLinear(b / 255);
      return 0.2126 * R + 0.7152 * G + 0.0722 * B;
    },

    _contrastRatio: function(L1, L2) {
      const [hi, lo] = L1 >= L2 ? [L1, L2] : [L2, L1];
      return (hi + 0.05) / (lo + 0.05);
    },

    /**
     * Shift brightness (L) by delta; negative delta = darker
     * Example delta for getting slightly darker hover color: "-0.08"
     * 
     * @param baseColor
     * @param deltaL
     * @returns {*|string}
     */
    shadeCssColor: function(baseColor, deltaL) {
      const rgba = this._cssColorToRgba(baseColor);
      if (!rgba) return baseColor; // Fallback: unverändert
      const hsl = this._rgbToHsl(rgba);
      hsl.l = Math.min(1, Math.max(0, hsl.l + deltaL));
      const rgb = this._hslToRgb(hsl);
      // Wenn die Eingabe alpha hatte, könntest du hier auch rgba(...) zurückgeben.
      return this._rgbaToHex(rgb); // hex ist hier am zuverlässigsten
    },

    /**
     * Returns a well readable text color (black or white) for a given background colour.
     *
     * @param backgroundCssColor
     * @param weight
     * Pass an optional weight [0..1] to favor a certain outcome: 
     *  - `0` means the return value is always `#ffffff` (white).
     *  - Values `(0..1)` weigh the contrast of the input color to black or white
     *  and returns the closest result. For example `0.4` would prefer white, but
     *  will return black if the contrast is large enough.
     *  - `1` means the return value is always `#000000` (black).
     *  
     *  Default value is `0.5`, i.e. equal weight for black and white.
     * @param backdropCssColor
     * Optional color assumed to be visible underneath the element. Transparent and
     * semi-transparent backgrounds are alpha-blended over it before measuring contrast.
     * Defaults to the theme background (CSS variable `--sapBackgroundColor`) or white.
     * @returns {string|string}
     */
    pickTextColorForBackgroundColor: function(backgroundCssColor, weight, backdropCssColor) {
      if (!backgroundCssColor) return '#000';
      let rgba = this._cssColorToRgba(backgroundCssColor);
      if (!rgba) return '#000';
      if (rgba.a < 1) {
        rgba = this._blendOverBackdrop(rgba, backdropCssColor);
      }

      const Lbg = this._relativeLuminance(rgba);
      const contrastToWhite = this._contrastRatio(1.0, Lbg);
      const contrastToBlack = this._contrastRatio(Lbg, 0.0);
      
      weight = weight === undefined || weight === null || (typeof weight !== 'number') ? 
          0.5 :
          Math.max(Math.min(weight, 1), 0);

      return (weight * contrastToBlack >= (1 - weight) * contrastToWhite) ? '#000' : '#fff';
    },
  }
  return exfColorTools;
})));