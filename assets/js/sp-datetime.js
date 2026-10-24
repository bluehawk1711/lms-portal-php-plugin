/*
 * Student Portal — reusable date & time input component (G3).
 *
 * Progressive enhancement for input[type=date][data-sp-date] and
 * input[type=time][data-sp-time]:
 *   - quick chips (Today / Yesterday / Clear) so past dates are one tap away;
 *   - a live long-date helper under the picker, with an off-day warning when
 *     the chosen weekday is one of the school's configured off days
 *     (data-sp-off-days="0,6"), which is what made "the day before class
 *     creation day" look broken — it was usually a Sunday;
 *   - ±15-minute steppers around time inputs;
 *   - min/max attributes are honoured (chips disable out-of-range dates) but
 *     no restriction is ever invented — that was the G2 complaint.
 *
 * Self-contained IIFE, DOMContentLoaded-safe, no dependencies. A lightweight
 * MutationObserver re-enhances markup inserted later (e.g. the admin calendar
 * sidebar fetched over admin-ajax).
 */
(function () {
  "use strict";

  function pad(n) {
    return (n < 10 ? "0" : "") + n;
  }

  function toYmd(date) {
    return (
      date.getFullYear() +
      "-" +
      pad(date.getMonth() + 1) +
      "-" +
      pad(date.getDate())
    );
  }

  function parseYmd(value) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec((value || "").trim());

    if (!m) {
      return null;
    }

    var d = new Date(+m[1], +m[2] - 1, +m[3]);
    return isNaN(d.getTime()) ? null : d;
  }

  function addDays(date, n) {
    var copy = new Date(date.getTime());
    copy.setDate(copy.getDate() + n);
    return copy;
  }

  function longLabel(date) {
    try {
      return date.toLocaleDateString(undefined, {
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric",
      });
    } catch (e) {
      return toYmd(date);
    }
  }

  function weekdayName(date) {
    try {
      return date.toLocaleDateString(undefined, { weekday: "long" });
    } catch (e) {
      return "";
    }
  }

  function offDaysOf(input) {
    var raw = (input.getAttribute("data-sp-off-days") || "").trim();

    if ("" === raw) {
      return [];
    }

    return raw
      .split(",")
      .map(function (part) {
        return parseInt(part, 10);
      })
      .filter(function (n) {
        return !isNaN(n) && n >= 0 && n <= 6;
      });
  }

  function boundDate(input, attr) {
    var raw = (input.getAttribute(attr) || "").trim();

    if ("" === raw) {
      return null;
    }

    // The common "max=today" style keywords resolve in the browser.
    if ("today" === raw) {
      return new Date();
    }

    return parseYmd(raw);
  }

  function inRange(input, date) {
    var min = boundDate(input, "min");
    var max = boundDate(input, "max");
    var t = date.getTime();

    if (
      min &&
      t < new Date(min.getFullYear(), min.getMonth(), min.getDate()).getTime()
    ) {
      return false;
    }

    if (
      max &&
      t > new Date(max.getFullYear(), max.getMonth(), max.getDate()).getTime()
    ) {
      return false;
    }

    return true;
  }

  function ready(input) {
    if ("1" === input.getAttribute("data-sp-datetime-ready")) {
      return false;
    }

    input.setAttribute("data-sp-datetime-ready", "1");
    return true;
  }

  function dispatchChange(input) {
    if ("function" === typeof Event) {
      input.dispatchEvent(new Event("change", { bubbles: true }));
    }
  }

  function enhanceDate(input) {
    if (!ready(input)) {
      return;
    }

    var wrap = document.createElement("span");
    wrap.className = "sp-dt";
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    var chips = document.createElement("span");
    chips.className = "sp-dt-chips";

    function chip(label, date) {
      var b = document.createElement("button");
      b.type = "button";
      b.className = "sp-dt-chip";
      b.textContent = label;
      b.addEventListener("click", function () {
        var d = "function" === typeof date ? date() : date;
        input.value = toYmd(d);
        dispatchChange(input);
        input.focus();
      });
      chips.appendChild(b);
      return b;
    }

    var today = new Date();

    chip("Today", function () {
      return new Date();
    }).setAttribute("data-sp-dt-today", "1");
    chip("Yesterday", function () {
      return addDays(new Date(), -1);
    }).setAttribute("data-sp-dt-today", "2");

    if (!input.required && !input.hasAttribute("required")) {
      var clear = document.createElement("button");
      clear.type = "button";
      clear.className = "sp-dt-chip sp-dt-chip-clear";
      clear.textContent = "Clear";
      clear.addEventListener("click", function () {
        input.value = "";
        dispatchChange(input);
      });
      chips.appendChild(clear);
    }

    var hint = document.createElement("span");
    hint.className = "sp-dt-hint";
    hint.setAttribute("aria-live", "polite");

    var offDays = offDaysOf(input);

    function refresh() {
      var chosen = parseYmd(input.value);

      // Chip availability follows the input's own min/max, never more.
      var todayBtn = chips.querySelector('[data-sp-dt-today="1"]');
      var yesterdayBtn = chips.querySelector('[data-sp-dt-today="2"]');

      if (todayBtn) {
        todayBtn.disabled = !inRange(input, today);
      }

      if (yesterdayBtn) {
        yesterdayBtn.disabled = !inRange(input, addDays(today, -1));
      }

      if (!chosen) {
        hint.textContent = "";
        hint.className = "sp-dt-hint";
        return;
      }

      if (-1 !== offDays.indexOf(chosen.getDay())) {
        hint.className = "sp-dt-hint is-offday";
        hint.textContent =
          longLabel(chosen) +
          " · Off day — no classes run on " +
          (weekdayName(chosen) || "this weekday") +
          ", so a register cannot be opened.";
      } else {
        hint.className = "sp-dt-hint";
        hint.textContent = longLabel(chosen);
      }
    }

    input.addEventListener("change", refresh);
    input.addEventListener("input", refresh);
    wrap.appendChild(chips);
    wrap.appendChild(hint);
    refresh();
  }

  function parseHm(value) {
    var m = /^(\d{1,2}):(\d{2})(?::\d{2}(?:\.\d+)?)?$/.exec(
      (value || "").trim(),
    );

    if (!m) {
      return null;
    }

    var h = +m[1],
      min = +m[2];

    if (h > 23 || min > 59) {
      return null;
    }

    return h * 60 + min;
  }

  function dispatchBoth(input) {
    if ("function" === typeof Event) {
      input.dispatchEvent(new Event("input", { bubbles: true }));
      input.dispatchEvent(new Event("change", { bubbles: true }));
    }
  }

  function enhanceTime(input) {
    if (!ready(input)) {
      return;
    }

    // `step` is in SECONDS; all maths below is in MINUTES.
    var stepSeconds = parseInt(input.getAttribute("step"), 10);

    if (isNaN(stepSeconds) || stepSeconds <= 0) {
      stepSeconds = 900; // 15 minutes.
    }

    var stepMin = Math.max(1, Math.round(stepSeconds / 60));

    var row = document.createElement("span");
    row.className = "sp-dt-time-row";
    input.parentNode.insertBefore(row, input);

    function stepper(symbol, direction, label) {
      var b = document.createElement("button");
      b.type = "button";
      b.className = "sp-dt-step";
      b.textContent = symbol;
      b.setAttribute("aria-label", label);

      b.addEventListener("click", function () {
        if (input.disabled || input.readOnly) {
          return;
        }

        // Start from the current value, or from "now" when empty.
        var current = parseHm(input.value);

        if (null === current) {
          var now = new Date();
          current = now.getHours() * 60 + now.getMinutes();
        }

        // Snap to the step grid: move to the next/previous slot,
        // or a full step when already on one.
        var rem = current % stepMin;
        var target;

        if (direction > 0) {
          target = current - rem + stepMin;
        } else {
          target = rem ? current - rem : current - stepMin;
        }

        // Respect the input's own min/max, else the day's bounds.
        var lo = parseHm(input.getAttribute("min"));
        var hi = parseHm(input.getAttribute("max"));

        lo = null === lo ? 0 : lo;
        hi = null === hi ? 24 * 60 - 1 : hi;

        target = Math.max(lo, Math.min(hi, target));

        input.value = pad(Math.floor(target / 60)) + ":" + pad(target % 60);
        dispatchBoth(input);
      });

      row.appendChild(b);
      return b;
    }

    stepper("\u2212" + stepMin + "m", -1, "Earlier by " + stepMin + " minutes");
    row.appendChild(input);
    stepper("+" + stepMin + "m", 1, "Later by " + stepMin + " minutes");
  }

  function run() {
    var dates = document.querySelectorAll('input[type="date"][data-sp-date]');

    for (var i = 0; i < dates.length; i++) {
      enhanceDate(dates[i]);
    }

    var times = document.querySelectorAll('input[type="time"][data-sp-time]');

    for (var j = 0; j < times.length; j++) {
      enhanceTime(times[j]);
    }
  }

  function boot() {
    run();

    // Re-enhance markup injected later (admin-ajax calendar sidebar, etc.).
    if ("undefined" !== typeof MutationObserver && document.body) {
      var queued = false;

      new MutationObserver(function () {
        if (queued) {
          return;
        }

        queued = true;
        window.setTimeout(function () {
          queued = false;
          run();
        }, 50);
      }).observe(document.body, { childList: true, subtree: true });
    }
  }

  if ("loading" === document.readyState) {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
