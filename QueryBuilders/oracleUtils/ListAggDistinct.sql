create or replace type TextAggregation as object (aggString VARCHAR2(32767),
	static function ODCIAggregateInitialize(sctx IN OUT TextAggregation) return number,
	member function ODCIAggregateIterate(self IN OUT TextAggregation, 
	value IN VARCHAR2) return number,
	member function ODCIAggregateTerminate(self IN TextAggregation, 
	returnValue OUT VARCHAR2, flags IN VARCHAR2) return number,
	member function ODCIAggregateMerge(self IN OUT TextAggregation, 
	ctx2 IN TextAggregation) return number
);

create or replace
TYPE BODY              "TEXTAGGREGATION" is
static function ODCIAggregateInitialize(sctx IN OUT TextAggregation) 
return number is
begin
  sctx := TextAggregation('');
  return ODCIConst.Success;
end;
 
member function ODCIAggregateIterate(self IN OUT TextAggregation, value IN VARCHAR2) return number is
  location number;
begin
    location := instr(', ' || aggString || ', ' , ', ' || value || ', ');
     
    if location > 0 then
        return ODCIConst.Success;
    end if;
     
  if (aggString is null) then
    aggString := value;
  else
    aggString := aggString || ', ' || value;
  end if;
   
    return ODCIConst.Success;
end;
 
member function ODCIAggregateTerminate(self IN TextAggregation, 
    returnValue OUT VARCHAR2, flags IN VARCHAR2) return number is
begin
  returnValue := self.aggString;
  return ODCIConst.Success;
end;
 
member function ODCIAggregateMerge(self IN OUT TextAggregation, ctx2 IN TextAggregation) return number is
begin
  self.aggString := ctx2.aggString;
  return ODCIConst.Success;
end;
end;

CREATE or replace FUNCTION ListAggDistinct (input VARCHAR2) RETURN VARCHAR2 PARALLEL_ENABLE AGGREGATE USING TextAggregation;