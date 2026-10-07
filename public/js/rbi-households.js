(() => {
    const normalize = value => String(value || '').trim().replace(/\s+/gu, ' ').toLocaleLowerCase();
    const resolve = (households, entered, currentId, originalHead, originalId) => {
        const matches = households.filter(household => normalize(household.label) === normalize(entered)
            || normalize(`${household.label} [Household ${household.household_number}]`) === normalize(entered));
        const selected = matches.length === 1 ? matches[0] : matches.find(household => String(household.id) === String(currentId));
        const retained = normalize(entered) === normalize(originalHead)
            ? households.find(household => String(household.id) === String(originalId)) : null;
        const household = selected || retained;
        return { selected, household, status: !normalize(entered) ? 'empty' : household ? 'matched' : matches.length > 1 ? 'ambiguous' : 'unmatched' };
    };
    const confirmUnmatched = (names, confirm) => {
        const unique = [...new Map(names.map(name => [normalize(name), name.trim()])).values()];
        return unique.length === 0 || confirm(`No matching household head was found in this barangay's registry for:\n\n${unique.map(name => `• ${name}`).join('\n')}\n\nCheck the spelling or select an existing household. Continue saving these entries with an unregistered household head?`);
    };
    window.RbiHouseholds = { resolve, confirmUnmatched };
})();
