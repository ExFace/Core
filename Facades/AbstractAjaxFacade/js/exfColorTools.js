;(function (global, factory) {
  typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory() :
      typeof define === 'function' && define.amd ? define(factory()) :
          global.exfColorTools = factory()
}(this, (function () { 'use strict';
  var exfColorTools = {

    // css-color -> {r,g,b,a} via offscreen element + getComputedStyle
    _cssColorToRgba: function(color) {
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

    // Shift brightness (L) by delta; negative delta = darker
    shadeCssColor: function(baseColor, deltaL) {
      const rgba = this._cssColorToRgba(baseColor);
      if (!rgba) return baseColor; // Fallback: unverändert
      const hsl = this._rgbToHsl(rgba);
      hsl.l = Math.min(1, Math.max(0, hsl.l + deltaL));
      const rgb = this._hslToRgb(hsl);
      // Wenn die Eingabe alpha hatte, könntest du hier auch rgba(...) zurückgeben.
      return this._rgbaToHex(rgb); // hex ist hier am zuverlässigsten
    },

    // determines the text color for given background color
    pickTextColorForBackgroundColor: function(backgroundCssColor) {
      if (!backgroundCssColor) return '#000';
      const rgba = this._cssColorToRgba(backgroundCssColor);
      if (!rgba) return '#000';

      const Lbg = this._relativeLuminance(rgba);
      const contrastToWhite = this._contrastRatio(1.0, Lbg);
      const contrastToBlack = this._contrastRatio(Lbg, 0.0);

      return (contrastToBlack >= contrastToWhite) ? '#000' : '#fff';
    },
  }
  return exfColorTools;
})));