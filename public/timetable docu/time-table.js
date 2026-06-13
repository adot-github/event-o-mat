// JS fallback: only used when CSS mod() or round() are NOT supported
(function () {
  const supportsRound = CSS.supports('width: round(down, 10px, 1px)');
  const supportsMod = CSS.supports('--number: mod(10, 3)');

  // If both features are supported, no fallback needed
  if (supportsRound && supportsMod) {
    return;
  }

  // ---- Fallback logic starts here ----

  const timetable = document.querySelector('.timetable');
  if (!timetable) return;

  const timetableStyles = getComputedStyle(timetable);
  const startTimeVar = timetableStyles.getPropertyValue('--start-time').trim();
  const startTimeHours = Number(startTimeVar) || 0;

  const minutesToTimelineStart = startTimeHours * 60;
  const unitMinutes = 5; // 5-minute grid steps

  function timeNumberToRow(raw) {
    const time = Number(raw);
    if (!Number.isFinite(time)) return null;

    // Same logic you use in CSS with round() + mod()
    const hours = Math.floor(time / 100);
    const minutes = time % 100;

    const totalMinutes = hours * 60 + minutes;
    const minutesRelative = totalMinutes - minutesToTimelineStart;

    return minutesRelative / unitMinutes + 1;
  }

  document.querySelectorAll('.session').forEach((session) => {
    const styles = getComputedStyle(session);

    const startRaw =
      session.style.getPropertyValue('--start') ||
      styles.getPropertyValue('--start');
    const endRaw =
      session.style.getPropertyValue('--end') ||
      styles.getPropertyValue('--end');

    const startRow = timeNumberToRow(startRaw.trim());
    const endRow = timeNumberToRow(endRaw.trim());

    if (startRow != null) session.style.gridRowStart = String(startRow);
    if (endRow != null) session.style.gridRowEnd = String(endRow);
  });
})();