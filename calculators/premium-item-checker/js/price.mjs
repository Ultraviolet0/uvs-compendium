import { prefixx, suffixx, premiumIndex } from './data.mjs';

// This table uses the original checker’s rounding and step order.
function formatDetailedPrice({ SelBasee, SelPref, SelSuff, detailedPrice, pricemin, psmulti, multi_sign, prerange, sufrange, premin, sufmin, prestep, sufstep }) {
  var i, j, m, n, nstep, sufsign, presign, price, priceminbase;
  var dsp3;
  dsp3 = "";

  if (SelSuff == 64) {
    sufmin = -sufmin;
    nstep = -1;
  } else {
    nstep = 1;
  }

  if (SelBasee && (detailedPrice) && (SelPref + SelSuff)) {

    if (prerange == 0) {
      if (sufmin > 0) {
        sufsign = "+";
      } else {
        sufsign = "";
      }

      if (sufrange) {
        if (SelSuff < premiumIndex.firstChargedSpellSuffix) {
          psmulti = 1;
        }

        dsp3 = dsp3 + suffixx[SelSuff].name;
        for (j = 0, n = sufmin; j <= sufstep; j++, n += nstep) {
          if (j % 5 == 0) {
            dsp3 += "\n";
          }
          price = pricemin + multi_sign * Math.floor(sufrange * (Math.floor(j * 100 / sufstep) / 100) * psmulti);
          dsp3 += "   " + sufsign + n + " : " + price;
        }
      }

    } else {

      if (premin > 0) {
        presign = "+";
      } else {
        presign = "";
      }

      if (sufrange) {
        if (sufmin > 0) {
          sufsign = "+";
        } else {
          sufsign = "";
        }

        if (SelSuff < premiumIndex.firstChargedSpellSuffix) {
          psmulti = 1;
        }

        priceminbase = pricemin;
        for (i = 0, m = premin; i <= prestep; i++, m++) {
          dsp3 += prefixx[SelPref].name + "   " + presign + m + "     ";
          pricemin = priceminbase + Math.floor(prerange * (Math.floor(i * 100 / prestep) / 100));
          dsp3 += suffixx[SelSuff].name;
          for (j = 0, n = sufmin; j <= sufstep; j++, n += nstep) {
            if (j % 5 == 0) {
              dsp3 += "\n";
            }
            price = pricemin + multi_sign * Math.floor(sufrange * (Math.floor(j * 100 / sufstep) / 100) * psmulti);
            dsp3 += "   " + sufsign + n + " : " + price;
          }
          dsp3 += "\n\n";
        }

      } else {
        dsp3 += prefixx[SelPref].name;
        for (i = 0, m = premin; i <= prestep; i++, m++) {
          if (i % 5 == 0) {
            dsp3 += "\n";
          }
          price = pricemin + Math.floor(prerange * (Math.floor(i * 100 / prestep) / 100));
          dsp3 += "   " + presign + m + " : " + price;
        }
      }

    }

  }

  return dsp3;
}

export { formatDetailedPrice };
