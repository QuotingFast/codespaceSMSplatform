## TCPA-Compliant 30-Day SMS Funnel (All ≤160 characters, no callback number, always include opt-out)

**Day 0:**  
Hi Clella, your quote for your 2002 MAZDA B3000 is ready! See savings: https://jan.gl/292vbd Reply STOP to opt out.

**Day 1:**  
Clella, discounts may apply for your MAZDA B3000 & drivers Elsworth, Jaleesa, Illya. See your quote: https://jan.gl/292vbd Reply STOP to opt out.

**Day 3:**  
Hi Clella, missed your insurance quote? Review & claim savings for your drivers: https://jan.gl/292vbd Reply STOP to opt out.

**Day 5:**  
Clella, still interested in insurance for your MAZDA B3000? See your quote: https://jan.gl/292vbd Reply STOP to opt out.

**Day 7:**  
Clella, final step for your insurance quote! See your savings: https://jan.gl/292vbd Reply STOP to opt out.

**Day 10:**  
Hi Clella, your quote is expiring soon. View your savings: https://jan.gl/292vbd Reply STOP to opt out.

**Day 14:**  
Clella, you may qualify for more savings. Review & claim: https://jan.gl/292vbd Reply STOP to opt out.

**Day 17:**  
Hi Clella, unlock discounts for your MAZDA B3000 & drivers. View quote: https://jan.gl/292vbd Reply STOP to opt out.

**Day 20:**  
Clella, maximize your savings! See your personalized quote: https://jan.gl/292vbd Reply STOP to opt out.

**Day 23:**  
Still looking for insurance, Clella? Your MAZDA B3000 quote is ready: https://jan.gl/292vbd Reply STOP to opt out.

**Day 26:**  
Clella, your discounts may expire soon. Review your quote: https://jan.gl/292vbd Reply STOP to opt out.

**Day 30:**  
Last chance, Clella! Your discounts for your MAZDA B3000 expire today. See savings: https://jan.gl/292vbd Reply STOP to opt out.

---

### Implementation Reminders

- **Opt-out:** On any inbound SMS with "STOP", immediately suppress all future SMS to that number.
- **No callback number in SMS.** All call-to-action is to the quote link.
- **Personalization:** Rotate names, vehicles, discounts, and link per lead.
- **Only send to leads with tcpa_compliant: true.**
- **Every message contains: "Reply STOP to opt out."**
- **All messages are ≤160 characters (including opt-out).**

---

You can generate these dynamically per lead using their data (name, vehicles, drivers, discounts, unique quote link).