"""Actual-only retraining with explicit readiness gating and an untouched final test."""
import argparse
from datetime import datetime, timezone
import hashlib
import html
import json
from pathlib import Path
import sys

if __package__ in (None,''):sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
import joblib
import numpy as np
from sklearn.model_selection import GridSearchCV,TimeSeriesSplit
from sklearn.base import clone
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import StandardScaler
from ml.converged_svr import CheckedSVR

GRID={'svr__C':[.01,.1,1.,10.],'svr__epsilon':[.1,.5,1.,2.],'svr__gamma':[.001,.01,.1,1.]}
TEST_MONTHS=5
MINIMUM_MONTHS=23  # Twelve initial observations, six validation targets, five final tests.


def write(path,value):path.write_text(json.dumps(value,indent=2,allow_nan=False),encoding='utf-8')


def latest_run(rows):
    runs=[];current=[]
    for row in rows:
        valid=row.get('verified') is True and type(row.get('count')) is int and row['count']>=0
        if valid and current:
            a=datetime.strptime(current[-1]['month'],'%Y-%m');b=datetime.strptime(row['month'],'%Y-%m')
            valid=(b.year-a.year)*12+b.month-a.month==1
            if not valid:runs.append(current);current=[];valid=True
        if valid:current.append(row)
        elif current:runs.append(current);current=[]
    if current:runs.append(current)
    eligible=[r for r in runs if len(r)>=MINIMUM_MONTHS]
    return eligible[-1] if eligible else []


def features(rows):
    # At each row, every count-based input uses earlier calendar months only.
    x=[];past=[]
    for row in rows:
        m=int(row['month'][5:]);fallback=float(np.mean(past)) if past else 0.
        x.append([np.sin(2*np.pi*m/12),np.cos(2*np.pi*m/12),past[-1] if past else 0.,
                  float(np.mean(past[-3:])) if past else 0.,past[-12] if len(past)>=12 else fallback,
                  int(len(past)<12)])
        past.append(row['count'])
    return np.array(x,dtype=float)


def measured(actual,predicted):
    e=np.asarray(actual)-np.asarray(predicted)
    return {'mae':float(np.mean(abs(e))),'rmse':float(np.sqrt(np.mean(e*e)))}


def train(rows,out):
    rows=latest_run(rows)
    if not rows:return {'status':'not_retrained_insufficient_verified_history','mae':None,'rmse':None,
        'previous_model_mae':None,'previous_model_rmse':None,'test_months':[],'required_consecutive_months':MINIMUM_MONTHS}
    x=features(rows);y=np.array([r['count'] for r in rows]);split=len(rows)-TEST_MONTHS
    train_x=x[:split];train_y=y[:split]
    cv=list(TimeSeriesSplit(n_splits=3,test_size=2).split(train_x))
    pipeline=Pipeline([('scale',StandardScaler()),('svr',CheckedSVR(kernel='rbf',max_iter=1000000))])
    search=GridSearchCV(pipeline,GRID,cv=cv,scoring={'mae':'neg_mean_absolute_error','rmse':'neg_root_mean_squared_error'},
        refit=False,n_jobs=1,error_score='raise')
    search.fit(train_x,train_y)
    index=min(range(len(search.cv_results_['params'])),key=lambda i:(-search.cv_results_['mean_test_mae'][i]-search.cv_results_['mean_test_rmse'][i])/2)
    chosen=search.cv_results_['params'][index]
    model=pipeline.set_params(**chosen).fit(train_x,train_y)
    out.mkdir(parents=True);joblib.dump(model,out/'model.joblib')
    write(out/'frozen-before-test.json',{'parameters':chosen,'training_through':rows[split-1]['month'],'test_used_for_selection':False,
        'model_sha256':hashlib.sha256((out/'model.joblib').read_bytes()).hexdigest()})
    # Model stays frozen. Earlier verified test actuals can enter later rolling inputs.
    prediction=np.maximum(0,model.predict(x[split:]))
    previous=np.array([rows[i-12]['count'] for i in range(split,len(rows))],dtype=float)
    metrics=measured(y[split:],prediction);baseline=measured(y[split:],previous)
    # Once evaluation is finished, refit the frozen settings on all now-known actuals.
    future_model=clone(model).fit(x,y)
    joblib.dump(future_model,out/'future_model.joblib')
    write(out/'future-fit.json',{'source_end':rows[-1]['month'],'actual_rows':len(rows),'parameters':chosen,
        'evaluated_with_this_refit':False,'model_sha256':hashlib.sha256((out/'future_model.joblib').read_bytes()).hexdigest()})
    return {'status':'retrained_retrospective_verified_history','parameters':chosen,**metrics,
        'previous_model_mae':baseline['mae'],'previous_model_rmse':baseline['rmse'],
        'previous_model':'Fixed Seasonal naive on the identical five test dates',
        'test_months':[r['month'] for r in rows[split:]],'training_through':rows[split-1]['month'],
        'test_predictions':[{'month':row['month'],'actual':row['count'],'predicted':float(p),'previous':float(b)}for row,p,b in zip(rows[split:],prediction,previous)],
        'validation_folds':[{'train_end':rows[a[-1]]['month'],'test_start':rows[b[0]]['month']}for a,b in cv],
        'protocol':'Frozen model, rolling one-month feature inputs; no claim of immutable historical as-of availability'}


def main():
    parser=argparse.ArgumentParser();parser.add_argument('directory',type=Path);args=parser.parse_args();out=args.directory
    if(out/'retraining-result.json').exists():raise FileExistsError('Use a fresh export')
    source=json.loads((out/'actual-source.json').read_text())
    assert source['format']=='rbim.actual_verified_training.v1' and source['synthetic_rows']==source['age_estimated_training_rows']==0
    write(out/'protocol.json',{'created_at_utc':datetime.now(timezone.utc).isoformat(),'grid':GRID,'minimum_consecutive_months':MINIMUM_MONTHS,
        'final_test_months':5,'folds':3,'fold_test_months':2,'training':'Verified nonnegative counts only; no dummy rows, age estimates or unverified zeros',
        'selection':'Earlier CV mean (MAE+RMSE)/2; final five test months never select parameters',
        'previous_model_comparison':'Fixed Seasonal naive on the exact same eligible target, scope and dates. Old Tinago preliminary sample scores are not comparable.'})
    results=[]
    for scope in source['scopes']:
        result={'id':scope['id'],'name':scope['name']}
        for target in ['in','out']:result[target]=train(scope[target]['rows'],out/'models'/str(scope['id']or'municipal')/target)
        results.append(result)
    payload={'results':results,'models_trained':sum(r[t]['status'].startswith('retrained_')for r in results for t in ['in','out']),
        'database_changed':False,'production_model_replaced':False,'synthetic_rows':0,'age_estimated_training_rows':0}
    write(out/'retraining-result.json',payload)
    table=''.join(f'<tr><th>{html.escape(r["name"])}</th><td>{t}</td><td>{r[t]["status"]}</td><td>{r[t]["mae"] if r[t]["mae"] is not None else "Not measurable"}</td><td>{r[t]["rmse"] if r[t]["rmse"] is not None else "Not measurable"}</td><td>{r[t]["previous_model_mae"] if r[t]["previous_model_mae"] is not None else "Not measurable"}</td><td>{r[t]["previous_model_rmse"] if r[t]["previous_model_rmse"] is not None else "Not measurable"}</td></tr>' for r in results for t in ['in','out'])
    doc=f'''<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Actual only migration retraining</title><style>body{{font:15px/1.6 Arial;background:#edf2f6;color:#234055}}main{{max-width:1200px;margin:24px auto;padding:28px;background:white}}table{{width:100%;border-collapse:collapse;font-size:12px}}th,td{{text-align:left;padding:8px;border-bottom:1px solid #ddd}}.notice{{background:#fff0d9;padding:16px}}.scroll{{overflow-x:auto}}</style><main><h1>Actual only migration retraining</h1>
<p class="notice">Models fitted: <b>{payload['models_trained']}</b>. Unknown errors remain unavailable. Importing registry profiles and unsigned RBI drafts does not establish complete migration training targets. No age based targets, simulated rows, assumed zero months or invented departure dates enter fitting.</p>
<p>Source cutoff {source['source_cutoff']}. Archive confirmed arrivals: {source['classified_archive']['confirmed_arrival_entries']} entries. Fully classified observed archive area months: {source['classified_archive']['fully_classified_observed_area_months']}; these are not independently reviewed complete movement months. Undated away or transferred profiles: {source['undated_away_or_transferred_profiles']}. Reports: {source['reports']['total']} total, {source['reports']['drafts']} drafts, {source['reports']['submitted']} submitted.</p>
<p>The actual source contains one dated non demo departure. The new remarks categories do not turn undated profiles into monthly departure events. First save arrival counts and physical arrival month counts remain distinct targets. Complete verified monthly history is required before comparing both models on the same five actual target months.</p>
<p>Protocol: at least 23 consecutive verified months, with the final five excluded from hyperparameter selection. StandardScaler and RBF SVR are fitted inside earlier chronological folds. Count inputs use only earlier months. The comparator is fixed Seasonal naive on the same scope and dates. Retrospective validation does not prove that historical database edits were available at each old origin.</p>
<div class="scroll"><table><thead><tr><th>Scope</th><th>Target</th><th>Status</th><th>New MAE</th><th>New RMSE</th><th>Previous MAE</th><th>Previous RMSE</th></tr></thead><tbody>{table}</tbody></table></div>
<p>Previous exploratory Tinago research: tuned 30 dataset RBF SVR MAE 3.4003, RMSE 4.7124; seasonal comparator MAE 3.0182, RMSE 4.2876. Those five previously inspected targets were preliminary age rule counts. They are not valid actual migration accuracy references for this run.</p>
<p>Next required data: classify NEW entries as actual in migration, newborn or late registration; resolve source dates; review complete months including genuine zeros; record actual departure dates. Save future forecasts before their targets occur and compare to later reviewed outcomes. A missing error is not zero error.</p>
<p><a href="actual-source.json">Aggregate actual source</a> · <a href="protocol.json">Frozen protocol</a> · <a href="retraining-result.json">Exact results</a></p></main></html>'''
    (out/'report.html').write_text(doc,encoding='utf-8')
    print(json.dumps({'models_trained':payload['models_trained'],'municipal':results[0],'report':str(out/'report.html')}))


if __name__=='__main__':main()
