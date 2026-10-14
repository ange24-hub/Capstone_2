# Assistant question coverage

The shared assistant includes a role-aware, expandable question catalog. Questions are routed to existing read-only summaries or reviewed workflow guidance. Optional local Ollama only classifies the question into an approved operation. Final wording, counts and links come from application templates and authorized database queries; model-written answers and SQL are never used. The older local narrator is not called by chat, including for full summaries.

The widget separates the conversation from a searchable question catalog, grouped into quick questions, accounts, role-specific workflows, GIS/migration, and privacy/limitations. Replies use a source label, report area/date, metric cards, numbered name lists, visible counting notes, and report buttons. Long replies start at their heading and scroll within the panel; the message box stays visible. Interpreted follow-up questions can be expanded within the reply. Text is rendered as text nodes, never HTML, and action links must point to the same application origin. `node --test tests/js/assistant-format.test.cjs` verifies numbering, literal markup, summary values, steps, and counting notes.

The reviewed FAQ now covers the 55 general, resident, secretary, municipal, GIS/migration and security questions listed below. English and Cebuano answers are application-owned guidance. They are matched before model classification and before live status/report handlers, so an instructional question cannot claim to have checked the user's account. Explore Questions includes the general/security questions and the relevant role's workflows. Other roles can read explanations, but only approved accounts with the required role and barangay assignment receive working page links. Exact catalog questions and reviewed Cebuano aliases are supported; arbitrary paraphrases still pass through the constrained interpreter or built-in handlers and may need clarification. Live-status questions remain separate from FAQ guidance.

Staff can ask about population, families, households, sex totals, age groups, seniors, PWD markers, encoding coverage, monthly migration, annual migration, and months with the most recorded events. Cebuano terms include tawo, pamilya, balay, bata, tigulang, lalaki and babaye. Existing approval and RBI summaries remain available.

Authorized, approved staff can also ask `hatagi kog names sa mga pwd` or `hatagi kog names sa senior citizens`. These requests use a dedicated database list handler before AI classification, so they cannot become a count-only answer or send fetched names to Ollama. The handler reuses the Population Reports PWD/senior sections: PWD uses recognized remarks; seniors use valid birth dates at age 60 or above, or recognized senior remarks. Lists include all registry statuses, matching the existing reports. Barangay staff see only their assigned barangay; municipal staff can select a registered barangay or see a municipal list. Resident, pending and unassigned barangay accounts receive no names. Chat shows names only (plus barangay labels for municipal lists), up to 50 records, with an explicit truncation note and links to the full report/PDF. Extra age, sex, occupation, date or status filters ask for clarification instead of being ignored. Empty results mean no matching encoded records, not confirmed absence of PWD or senior citizens. Name lists clear report follow-up context rather than turning a later PDF request into a count-only report.

Specific population questions show only selected metric counts and their relevant counting notes. Explicit metrics in the question take priority over a conflicting model classification. Family answers retain the single-person rule and incomplete-family warning. Senior and PWD answers retain their distinct birth-date/remarks bases. Children means ages 0-17 and adults means 18+ using valid birth dates; unknown dates are disclosed. All counts use the existing PopulationSummary snapshot across all statuses, and full report/PDF links remain available. Full summaries also use application templates. AssistantPopulationAnswerTest checks count rules, missing data, scope, report links, no model requests for focused answers, and unchanged registry records.

Migration accepts a month name, YYYY-MM, this month, or last month (including the January/December year boundary). Multiple months and invalid numeric months require clarification. The linked migration PDF is still an annual report with monthly rows. Event counts are not unique-person counts or seasonal forecasts.

Workflow guidance covers document requests, configured fees, GCash proof submission, request status meanings, profile changes, PDF downloads, and family/SC/PWD counting rules. Resident accounts receive guidance and their own existing request summary, not staff report facts. Barangay report scope remains limited to the assigned barangay.

Document status questions now support pending, processing, ready, completed, and rejected counts, plus separate payment-status counts. Approved residents see only their own requests and approved barangay staff see their assigned barangay. Municipal accounts do not receive individual document status data. Counts explicitly cover all recorded dates. A resident question such as “Ready na ba akong request?” returns the actual latest request status and payment status, with guidance to open My Requests for other requests. Chat never marks a request ready or paid. AssistantDocumentStatusTest verifies these boundaries and unchanged request records.

After a successful migration answer, short date follow-ups such as “ug last month?”, “and November?” or “2025-12” retain its barangay. Month names keep the selected year; relative dates use the current calendar date. “Download PDF” after a population or migration answer repeats that report with its download link. The interpreted question is displayed. Only a canonical topic/scope/date string is kept in the current page's memory; no answer history or resident details are stored for this feature. New conversation, page reload, an unrelated answer, or a failed request clears this context. All follow-ups pass through the same authorization checks; client context is never permission to read records. AssistantFollowUpTest covers year boundaries, report links, forged cross-barangay context, resident restrictions, and context clearing.

Population follow-ups such as “ug senior?”, “Pila pud ang PWD?” and “ug lalaki ug babaye?” now retain the selected barangay. Municipal users can switch it with “how about Canlupao?” or “sa Canlupao?”, preserving selected metrics (or the migration period). “Full summary” restores the complete report. A scope change still passes through normal barangay authorization. Missing context receives a prompt for a complete question. Pending barangay accounts cannot use legacy RBI, approval or registry summaries to bypass approval requirements.

Chat controls clear context on reset or request failure, stop waiting after 35 seconds, and explain expired-session and rate-limit responses. AssistantCatalogTest exercises every catalog question for all three account roles and renders the shared widget. Other follow-ups still need a complete question. Unsupported topics, historical population snapshots, forecasting, and arbitrary age ranges are not newly implemented. Existing population reports show their standard age groups. The standalone Behavioral Analysis feature has been removed; see behavioral-analysis.md.

Scope checks run before model interpretation and also protect the built-in assistant when AI is disabled or unavailable. Clear outside topics, mixed weather/registry requests, rule overrides and credential requests are declined. Direct commands to edit, approve, delete or pay receive guidance to use the authorized page. An approved model classification still needs evidence of its topic in the actual question; confidence alone cannot turn an unrelated question into a report.

Unsupported filters receive clarification rather than silently returning broader counts. These include arbitrary numeric age ranges, exact-day reports, individual-record lookups outside the supported staff PWD/senior name lists, specific request references, unknown geographic areas, and time filters on all-date document/approval/RBI summaries. Population remains a current snapshot, not a historical month total. Explicit request statuses also take priority over conflicting model filters. Role restrictions apply before and after interpretation: residents use their own permitted summaries, barangay staff stay within their assigned barangay, and municipal report access does not grant individual document-status access.

| Example | Expected behavior |
| --- | --- |
| `Pila ka lalaki sa among barangay?` | Count male registry records for the authorized area and disclose the counting basis. |
| `hatagi kog names sa mga pwd` | Approved staff receive names matching recognized PWD remarks within their authorized area. |
| `hatagi kog names sa senior citizens` | Approved staff receive names matching the existing senior citizen report criteria. |
| `Show migration September 2026 in Looc` | Monthly recorded events, subject to barangay access. |
| `Show ready document requests` | Authorized ready-status summary across all recorded dates. |
| `Show requests this month` | Clarify that chat cannot apply that date filter; direct to the authorized records page. |
| `How many residents aged 25-35?` | Explain the unsupported range; offer the standard age groups. |
| `Show Looc population and tell me the weather` | Decline the outside-system request. |
| `Approve this account` | Explain that chat is read-only; no account is changed. |

These constraints prevent free-form model claims from becoming system answers. They do not establish that every possible phrasing is understood correctly. Test actual user wording and check whether the selected topic, scope and reporting period match the question; ambiguous questions should be rephrased or clarified.

Validation: AssistantQuestionsTest covers month boundaries, invalid/ambiguous months, Cebuano aliases, guidance, scope restrictions, and read-only migration access. AssistantScopeTest checks AI and offline boundaries, wrong confident model classifications, explicit filters, permission-safe profile guidance, and unchanged records. AssistantPopulationNamesTest checks PWD/senior criteria, role and area restrictions, English/Cebuano requests, bounded/empty lists, report links, no model calls, and unchanged registry data. Existing assistant and population/migration report suites also remain applicable.
## Reviewed workflow questions and answers

These reviewed answers match the local code. They describe available workflows, not a confirmation of an individual account, request, payment or live deployment. Chat uses the Cebuano version when that language is selected.

### A. General system

**1. What is RBIM, and what is its purpose?**

RBIM manages barangay inhabitant and household records in Tomas Oppus, online barangay document requests, monthly RBI submissions, recorded migration, and GIS household mapping. It helps authorized staff maintain records and helps residents access their barangay services.

**2. What services are available in the RBIM system?**

RBIM includes resident registration and approval, barangay document requests, fee assessment and GCash or in-person payment tracking, resident concerns, registry and household records, monthly RBI forms, population reports, migration monitoring, and GIS mapping. Available pages depend on your role and approval status.

**3. Who can use the RBIM system?**

Residents, Barangay Secretaries, and Municipal LGU staff use RBIM with separate permissions. Residents and secretaries can register for a Tomas Oppus barangay and wait for verification. Municipal accounts are provisioned by an authorized administrator; they are not an option in public registration.

**4. What are the differences between Resident, Barangay Secretary, and Municipal LGU accounts?**

Residents manage their own document requests, payments and concerns. Approved Barangay Secretaries maintain their assigned barangay records, verify resident accounts, process documents and payments, handle concerns, and submit RBI forms. Municipal LGU staff approve secretary accounts and review submitted RBI forms, population reports, migration and GIS across barangays. Municipal access does not include opening individual resident document requests in this workspace.

**5. Is RBIM available to all 29 barangays of Tomas Oppus?**

RBIM is designed for the 29 barangays of Tomas Oppus. Registration uses the configured Tomas Oppus barangay directory. Each secretary is assigned to a barangay, while municipal reports can cover all configured barangays. This does not confirm that every barangay has complete encoded records or an active staff account.

**6. How can I create an account in RBIM?**

Sign out if already signed in, then choose Register on the login page. Select Resident or Barangay Secretary, provide your name, email, Tomas Oppus barangay, password and confirmation. A secretary also needs a unique staff user ID and the official access code from an authorized administrator. Resident approval is handled by barangay staff; secretary approval is handled by the Municipal LGU. Public registration cannot create a Municipal LGU account.

**7. What should I do if I forget my password?**

Sign out and choose Forgot Password on the login page. Enter your registered email and follow the reset link if delivered. Check spam and contact your barangay or system administrator if the email does not arrive. Reset delivery depends on the configured mail service; chat cannot reveal or reset your password.

### B. Resident accounts

**8. How can I register as a resident?**

On the Register page, choose Resident, enter your name and email, select your actual Tomas Oppus barangay, and set and confirm your password. Submit the form and wait for your barangay to verify the account. A resident does not need the secretary access code. Registration does not itself create an official inhabitant registry record.

**9. How will I know if my resident account has been approved?**

Sign in and check the account approval page or ask "What is my account status?" for your actual recorded status. An approved resident can open the Resident Portal; a pending account remains on the approval page. Your assigned barangay makes the approval decision. I have not checked your status in this instructional answer.

**10. How can I request a barangay document online?**

An approved resident can open Request Document, choose a supported document type, enter the purpose and required information, and submit. Follow the request in My Requests. Wait for fee assessment where applicable, then follow the payment instructions. The request is processed by your assigned barangay; chat does not submit it for you.

**11. What types of documents can I request through RBIM?**

The supported document types are Barangay Indigency, Barangay Clearance, Certificate of Residency, and Business Clearance. RBIM does not directly issue PSA birth certificates or documents outside these request types. Requirements and release arrangements are handled by your barangay.

**12. How can I check the status of my document request?**

Open My Requests in the Resident Portal and check the specific request reference, document status, payment status and staff remarks. You can also ask "What is my document request status?" for your latest authorized request. Document status and payment verification are separate; this guidance does not confirm your actual request status.

**13. How do I know if my document request has been approved or rejected?**

Check the recorded status and remarks in My Requests. The available document statuses are pending review, processing, ready for release, completed, and rejected; there is no separate "approved" document status. Read the remarks if rejected. Processing or a verified payment does not itself mean the document is ready for collection.

**14. How can I pay for my requested document using GCash?**

Wait for the secretary to set the fee in My Requests. If the barangay has activated its official GCash details, pay the displayed amount to that account and submit the payment information and receipt through the request. You can instead choose in-person payment when available. Barangay Indigency is free. Chat cannot process a payment or verify a receipt.

**15. How can I upload my proof of payment?**

Open the payment form for your own request after its fee is set. Enter the payer name, Philippine mobile number beginning with 09, 13-digit GCash reference, and transaction date/time. Upload a JPG, JPEG or PNG receipt image up to 5 MB and submit for barangay verification. A PDF is not accepted as GCash proof. The barangay GCash service must be activated.

**16. What should I do if my payment is not yet verified?**

Check the payment status and remarks in My Requests or Payment History. While verification is pending, do not pay again; contact your barangay with the request and transaction references. If rejected, review the payment remarks and correct the submission as instructed. Only authorized barangay staff can verify a payment; this answer does not check yours.

**17. Can I view or update my personal information?**

You can ask "What is my account status?" to view your own account summary. Resident accounts do not have a My Profile editor in this system; contact your barangay for other account or official registry corrections. Residents can reset their password through Forgot Password after signing out. Barangay and municipal staff have My Profile for their name, email and password. Account data and official inhabitant records are separate.

**18. How can I submit a concern to my barangay?**

An approved resident with an assigned barangay can open My Concerns and choose New Concern. Enter a title, category and description, optionally a location and a PDF/JPG/JPEG/PNG attachment up to 5 MB, then submit. Keep the concern reference to track updates. This inbox is for service concerns and is not an emergency response service.

**19. How can I check whether my submitted concern has been addressed?**

Open My Concerns and select your concern to read its recorded status and the barangay public updates. You can add information while the concern is not closed. Staff-only internal notes are not shown to residents. A recorded status does not by itself prove that an issue was resolved in the real world; contact the barangay if the update needs clarification.

### C. Barangay Secretaries

**20. How can I add a new resident to the system?**

An approved secretary uses Add Residents in the RBI workspace to enter the reporting month, household/family and new inhabitant details. Save and review the monthly form; the separate consolidation action adds verified entries to Consolidated RBI. Existing registry forms also support staff encoding within the assigned barangay. Do not confuse a resident login account with an inhabitant record, or assume that saving a draft already consolidates it.

**21. How do I register a new household?**

Approved barangay staff can select an existing household or enter a new household number when encoding verified inhabitant/family records. Check the household number, family links and address before saving. Authorized GIS users can also add a recorded household and coordinates on the map form. A map building point is not automatically a registered household, and a household does not automatically create resident login accounts.

**22. How can I update an existing resident's information?**

An approved secretary opens Consolidated RBI for the assigned barangay, finds the inhabitant, chooses Edit, checks the details and saves the authorized form. Registry edits are recorded in activity history. Monthly RBI entries have their own editing workflow. Chat cannot edit the record or change another barangay record.

**23. How can I record a deceased resident?**

An approved secretary opens Deceased Inhabitants in the RBI workspace, selects the reporting month and the correct household/registered inhabitant where available, enters the name and verified date of death, and saves or submits the monthly form with the required certification. Review existing entries and duplicate warnings first. Chat does not mark an inhabitant deceased or automatically remove a registry record.

**24. What should I do if the system detects a duplicate resident record?**

Review the warning and compare the identity, birth date, household/family and reporting month against the existing record. Reuse or edit the existing monthly report when appropriate and remove only a confirmed repeated form entry before saving. People with the same name can be different people. Duplicate checks do not automatically merge or delete official records, and chat cannot override the warning.

**25. How can I generate the Registry of Barangay Inhabitants (RBI) report?**

An approved secretary opens RBI Forms/History, prepares or edits the reporting month, verifies family members and deceased entries, and completes the required certification and signatures. Submit the monthly form to the Municipal LGU when ready. Open the saved report to use the available download, PDF or Word export. Consolidating entries into the registry is a separate staff action.

**26. How can I generate a monthly population report?**

Population Reports provides a current database snapshot and its PDF/export; it cannot reconstruct historical population totals for a selected month. For monthly newly registered/deceased inhabitant submissions, use the monthly RBI form and its reporting month. Monthly migration events are available separately in Migration Reports. Do not label the current population snapshot as last month's population.

**27. How can I filter residents who are senior citizens, PWDs, or indigent?**

Population Reports has Senior Citizens and PWD sections with resident lists. Seniors use valid birth dates at age 60 or above, or recognized senior remarks; PWD uses recognized PWD remarks. Authorized staff can also ask for their names in chat. A dedicated indigent section is not implemented in Population Reports; review recorded indigency remarks through the authorized records workflow. Missing remarks do not prove absence of disability or indigency.

**28. How do I approve or reject a resident registration?**

An approved secretary opens Resident Approvals, checks the applicant belongs to the assigned barangay and verifies the submitted information. Use Approve or Reject on that account through the authorized page. Approval enables resident portal services; it does not automatically create an official inhabitant record. Chat cannot make the approval decision or update the account.

**29. How can I process and approve document requests?**

An approved secretary opens Document Requests for the assigned barangay, reviews the request and updates its recorded status and remarks. Assess the fee where required, verify GCash or confirm in-person payment, then mark the document ready for release or completed as appropriate. The system prevents ready/completed status without the required verified payment. Rejection and document preparation are staff decisions; chat does not approve or issue documents.

**30. How can I verify a resident's GCash payment?**

An approved secretary opens the assigned barangay request with a payment awaiting verification and reviews the receipt, payer information, reference, transaction date and assessed amount against the actual payment received. Choose Verify or Reject; rejection requires remarks. Uploading an image is not automatic proof of receipt. Payment verification is separate from marking a document ready for release.

**31. How can I respond to a concern submitted by a resident?**

Approved barangay staff open Concerns for the assigned barangay, review the submission and use the allowed status/category update form. Enter a public message for the resident; put staff-only information in the separate internal note field. Review the latest version before saving. An optional AI category suggestion must be reviewed by staff; it does not decide urgency or resolution.

### D. Municipal LGU

**32. What is the role of the Municipal LGU in RBIM?**

Municipal LGU staff verify Barangay Secretary accounts, review submitted monthly RBI forms, monitor population and migration across barangays, access GIS mapping, and review aggregate Concern Insights. The municipal workspace does not process individual resident document requests or replace barangay staff verification of their official records.

**33. How can the Municipal LGU monitor barangay population records?**

Authorized municipal staff open Population Reports to view the available registry snapshot and encoding coverage, then select a barangay or municipality-wide scope. Submitted monthly RBI forms are available separately in the municipal dashboard. Counts depend on encoded records and do not establish a complete census or historical month totals.

**34. How can I view population statistics from different barangays?**

Authorized municipal staff can select a barangay in Population Reports or use its Summary by Barangay section to compare available encoded counts. Chat supports one named barangay per population question or a municipal summary. A barangay secretary remains restricted to the assigned barangay, and residents cannot open staff population reports.

**35. How can I review reports submitted by Barangay Secretaries?**

Authorized municipal staff open the Municipal Dashboard, select a submitted RBI report and review its reporting month, barangay, household/family entries and certification. Use the report's available exports when needed. Municipal access is limited to submitted forms; a secretary's private draft is not a submitted municipal report.

**36. How can I monitor in-migration and out-migration across barangays?**

Authorized municipal staff open Migration Monitoring, choose the reporting year and an optional barangay, and review recorded in/out events and monthly totals. Migration Reports also provides an annual report/PDF with monthly rows. Barangay secretaries see their assigned barangay only. Event counts are not necessarily unique people; missing events do not confirm zero actual movement.

**37. How can I approve a Barangay Secretary account?**

An authorized municipal user opens Account Approvals, verifies the applicant identity, staff user ID and assigned barangay, then uses the approved page to approve or reject the secretary account. The public secretary access code alone does not grant approval. Residents and secretaries cannot approve secretary accounts, and chat cannot perform this action.

**38. How can I view municipality-wide population summaries?**

Authorized municipal staff open Population Reports with no individual barangay selected, or ask "Show municipal population summary". The result covers available records across the configured barangays and includes encoding/completeness notes. It is a current registry snapshot, not a verified complete municipal census. Residents do not have access to this staff report.

**39. How can I use the reports for municipal planning and decision-making?**

Use authorized population, coverage, recorded migration and submitted RBI reports to identify service needs and gaps in encoding. Check the area, reporting period, definitions and completeness before comparing figures. Verify gaps with barangay staff and use forecasts only as provisional planning estimates. The reports do not make policy decisions or establish individual eligibility automatically.

### E. GIS and migration

**40. What is the purpose of GIS mapping in RBIM?**

GIS mapping helps authorized barangay and municipal staff view recorded household locations alongside available barangay boundaries, building points and hazard layers. It supports spatial review of encoded records. Source building points are separate from registered households and must not be treated as confirmed occupied homes or population totals.

**41. How can authorized users locate recorded households on the map?**

Authorized barangay and municipal staff open GIS/Spatial Visualization, select the permitted area and use the registered household markers to inspect recorded locations. Only households with valid saved latitude and longitude appear as registered map markers. Missing/invalid coordinates appear in the unmapped records list and need staff verification. Barangay accounts cannot inspect another barangay through this map.

**42. Can GIS mapping show households with senior citizens or PWDs?**

The current GIS map shows registered household markers and resident information within staff permissions, but it does not implement a dedicated senior/PWD household filter or layer. Use the Senior Citizens or PWD sections of Population Reports for the verified lists, then review authorized household records. Do not infer disability or senior status from a building point or satellite image.

**43. What is the difference between in-migration and out-migration?**

In-migration is a recorded move into the reporting barangay; out-migration is a recorded move out of it. Net change is in-migration minus out-migration. These are movement events, not necessarily unique people. A temporary visit or stay for work/school should not automatically be encoded as an official transfer without staff verification.

**44. How can Barangay Secretaries record a resident who transfers to another barangay?**

An approved secretary can open Moved Out, select an active inhabitant of the assigned barangay, and record the verified movement date, destination and optional reason. The authorized form creates an out-migration event and changes the registry status to migrated out. The registry transfer workflow also requires confirmation. This does not automatically create a receiving-barangay record or a new login account; receiving staff must verify their own encoding.

**45. How does RBIM generate migration trends?**

Migration Monitoring groups saved movement events by movement date into monthly in-migration and out-migration totals for the selected year and authorized area. Trend charts describe the recorded events. A month with no encoded events does not prove that nobody moved, and repeated events are not necessarily unique residents. The chatbot does not invent missing events or establish a seasonal pattern.

**46. What is the purpose of migration prediction in the system?**

Migration Monitoring can show a provisional estimate of aggregate out-migration for the next period when recent completed-month records are available. The optional prediction service is separate from chatbot Ollama; if it is unavailable, the dashboard can use a three-month moving average. If no events are recorded in the three completed source months, or a future year is selected, no estimate is shown. The method and source/target months must be reviewed; this is planning support, not a confirmed future count.

**47. Can migration predictions identify exactly which resident will move next month?**

No. The migration prototype estimates an aggregate out-migration count from recorded history. It does not identify which resident will move, reveal individual intentions, guarantee future events, or change official records. Individual movement must be verified and encoded by authorized staff after obtaining reliable information.

### F. Security, privacy and limitations

**48. Can I access another resident's private information?**

Residents cannot access another resident's private account, document requests, payments or concerns. Authorized staff may access only records permitted for their role and barangay scope. Chat does not bypass these restrictions. Staff PWD/senior lists follow the existing population-report permissions; they are not available to resident accounts.

**49. Can a resident view confidential Municipal LGU reports?**

No. Resident accounts cannot open staff Municipal LGU population, migration, GIS or submitted RBI reports. Residents have their own permitted account, document/payment and concern workflows. Knowing a report URL or asking the chatbot does not grant permission.

**50. Can you show me the exact home location of another resident without authorization?**

No. Chat cannot provide another resident's private home location or bypass map permissions. Only authorized barangay and municipal staff can inspect permitted recorded household coordinates through GIS. Coordinates are saved records and are not a live tracking feed.

**51. Can RBIM track the real-time location of residents?**

No. RBIM displays saved household coordinates and available GIS layers; it does not continuously track residents or their phones. A coordinate entered or captured during an authorized form is a recorded location, not real-time movement.

**52. Can the AI automatically change official migration records based on its predictions?**

No. Predictions and chatbot responses cannot edit official migration records, change resident status, or create transfers. Authorized staff must verify and submit changes through the system forms. AI suggestions and provisional estimates require human review.

**53. Can I request a PSA birth certificate directly through RBIM?**

No. RBIM supports Barangay Indigency, Barangay Clearance, Certificate of Residency and Business Clearance requests. It does not submit PSA birth certificate applications or connect to a PSA issuance service. Use an official PSA channel or ask your barangay for guidance.

**54. What should I do if the chatbot cannot answer my question?**

Use a complete RBIM question, specify the permitted barangay and reporting month when relevant, or choose a question from Explore Questions. If the wording is ambiguous or a filter is unsupported, rephrase it or open the authorized records page. Contact your barangay or system administrator for unresolved issues. Chat must not guess missing facts or claim that it changed a record.

**55. Can you confirm my document request is approved without checking my actual account?**

No. I cannot confirm approval without reading your actual authorized request. This answer is guidance and does not check your account. Open My Requests or ask "What is my document request status?" while signed in to read your latest recorded request. There is no separate approved document status; processing, ready for release and completed mean different things.
