(() => {
 const forms = [...document.forms].filter(f => (f.hasAttribute('data-residence-update') || /\/registry\/\d+$/.test(new URL(f.action).pathname)) && f.querySelector('[name="_method"]')?.value === 'PUT');
 forms.forEach(form => {
  const fields = [...form.elements], original = new Map(fields.map(f => [f, f.value])); let submitting = false;
  const reset = () => fields.forEach(f => { f.value = original.get(f); });
  form.addEventListener('submit', event => {
   if (submitting) return;
   const remark = form.elements.namedItem('remarks')?.value || '';
   const match = !/\b(?:NOT|NO|NEVER)\s+TRANSFER/i.test(remark) && remark.match(/\bTRANSFER(?:RED|ED)\s+TO\s+([^\[\r\n]+)/i);
   const transfer = form.elements.namedItem('residence_status')?.value === 'transferred' || match;
   let destination = match ? match[1].trim() : '';
   if (transfer && !destination) destination = window.prompt('Transferred to where? Enter the destination.');
   if (transfer && !destination?.trim()) { event.preventDefault(); reset(); return; }
   const message = transfer ? `Are you sure you want to transfer this resident to ${destination}? They will leave Consolidated RBI and appear in Moved Out.` : 'Are you sure you want to save these changes?';
   if (!window.confirm(message)) { event.preventDefault(); reset(); return; }
   if (transfer) for (const [name, value] of Object.entries({transfer_destination: destination.trim(), transfer_confirmed: '1'})) {
    const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; form.append(input);
   }
   submitting = true;
  });
  if (form.id.startsWith('rbi-row-') || form.hasAttribute('data-residence-update')) fields.forEach(field => {
   if (field.type !== 'hidden') field.addEventListener('change', () => { if (!submitting && field.value !== original.get(field)) form.requestSubmit(); });
  });
 });
})();
